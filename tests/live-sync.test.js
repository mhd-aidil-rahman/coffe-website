import test from 'node:test';
import assert from 'node:assert/strict';
import { startLiveSync } from '../resources/js/live-sync.js';

const flush = () => new Promise((resolve) => setImmediate(resolve));

function browser() {
    const state = { revision: 'initial', html: 'new order', editing: false, offline: false, requests: [], events: [], redirects: [] };
    const target = { dataset: { liveSync: 'orders' }, id: 'orders', innerHTML: 'old orders', contains: () => state.editing };
    const status = {};
    const listeners = new Map();
    const document = {
        hidden: false,
        activeElement: { matches: () => state.editing },
        querySelectorAll(selector) {
            if (selector === '[data-live-sync]') { return [target]; }
            if (selector === '[data-live-sync-status]') { return [status]; }
            return [];
        },
        querySelector(selector) {
            if (selector === 'meta[name="live-sync-url"]') { return { content: 'http://test/live-sync' }; }
            if (selector === 'meta[name="live-sync-revision"]') { return { content: 'initial' }; }
            if (selector === '.modal-overlay.show') { return state.modal ? {} : null; }
            return null;
        },
        addEventListener(name, handler) { listeners.set(name, handler); },
        removeEventListener(name) { listeners.delete(name); },
        dispatchEvent(event) { state.events.push(event); },
    };
    const window = {
        document,
        location: { href: 'http://test/owner/dashboard', assign: (url) => state.redirects.push(url) },
        setTimeout, clearTimeout,
        setInterval(handler) { state.tick = handler; return 1; },
        clearInterval() {},
        addEventListener() {}, removeEventListener() {},
    };
    const fetch = async (url) => {
        state.requests.push(String(url));
        if (state.offline) { throw new Error('offline'); }
        if (String(url).includes('/live-sync')) {
            return { ok: !state.unauthorized, status: state.unauthorized ? 401 : 200, json: async () => ({ data: { revision: state.revision } }) };
        }
        return { ok: true, url: String(url), text: async () => state.html };
    };
    globalThis.DOMParser = class {
        parseFromString(html) { return { querySelector: () => ({ innerHTML: html }) }; }
    };
    globalThis.CustomEvent ??= class extends Event { constructor(name, options) { super(name); this.detail = options.detail; } };
    return { state, target, status, window, document, fetch };
}

test('Unchanged data uses only the lightweight revision endpoint', async () => {
    const env = browser();
    const stop = startLiveSync(env);
    await flush();
    env.state.tick();
    await flush();
    assert.equal(env.state.requests.length, 2);
    assert.ok(env.state.requests.every((url) => url.endsWith('/live-sync')));
    assert.equal(env.target.innerHTML, 'old orders');
    stop();
});

test('Remote changes update the open page and dispatch the cart update event', async () => {
    const env = browser();
    const stop = startLiveSync(env);
    await flush();
    env.state.revision = 'new-order';
    env.state.tick();
    await flush();
    assert.equal(env.target.innerHTML, 'new order');
    assert.equal(env.state.events[0].detail.key, 'orders');
    const reads = env.state.requests.length;
    env.state.tick();
    await flush();
    assert.equal(env.state.requests.length, reads + 1);
    stop();
});

test('Editing forms and open modals defer updates until the user finishes', async () => {
    const env = browser();
    const stop = startLiveSync(env);
    await flush();
    env.state.revision = 'new-order';
    env.state.editing = true;
    env.state.tick();
    await flush();
    assert.equal(env.state.requests.length, 1);
    env.state.editing = false;
    env.state.modal = true;
    env.state.tick();
    await flush();
    assert.equal(env.target.innerHTML, 'old orders');
    env.state.modal = false;
    env.state.tick();
    await flush();
    assert.equal(env.target.innerHTML, 'new order');
    stop();
});

test('Hidden pages pause checks and expired sessions return to login', async () => {
    const env = browser();
    env.document.hidden = true;
    const stop = startLiveSync(env);
    await flush();
    assert.equal(env.state.requests.length, 0);
    env.document.hidden = false;
    env.state.unauthorized = true;
    env.state.tick();
    await flush();
    assert.deepEqual(env.state.redirects, ['/login']);
    stop();
});

test('Failed requests retry and preserve the last revision until synchronization succeeds', async (t) => {
    t.mock.method(console, 'error', () => {});
    const env = browser();
    const stop = startLiveSync(env);
    await flush();
    env.state.offline = true;
    env.state.tick();
    await flush();
    assert.match(env.status.textContent, /tertunda/);
    env.state.offline = false;
    env.state.revision = 'recovered';
    env.state.tick();
    await flush();
    assert.equal(env.target.innerHTML, 'new order');
    stop();
});
