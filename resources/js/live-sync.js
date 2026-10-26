export function startLiveSync({
    window = globalThis.window,
    document = window.document,
    fetch = window.fetch.bind(window),
    interval = 1000,
} = {}) {
    const targets = Array.from(document.querySelectorAll('[data-live-sync]'));
    const syncUrl = document.querySelector('meta[name="live-sync-url"]')?.content;
    if (targets.length === 0 || !syncUrl) {
        return () => {};
    }

    let revision = document.querySelector('meta[name="live-sync-revision"]')?.content;
    let refreshing = false;
    let stopped = false;
    let controller;
    const setStatus = (message) => {
        document.querySelectorAll('[data-live-sync-status]').forEach((status) => {
            status.textContent = message;
        });
    };
    const isEditing = () => document.querySelector('.modal-overlay.show')
        || (document.activeElement?.matches('input:not([type="hidden"]), textarea, select, [contenteditable="true"]')
            && targets.some((target) => target.contains(document.activeElement)))
        || Array.from(document.querySelectorAll('.menu-action-menu')).some((menu) => menu.style.display === 'block');

    const check = async (force = false) => {
        if (stopped || refreshing || document.hidden || isEditing()) {
            return;
        }
        refreshing = true;
        controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), 10000);
        try {
            const options = {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal,
            };
            const syncResponse = await fetch(syncUrl, options);
            if (syncResponse.status === 401 || syncResponse.status === 403) {
                stopped = true;
                window.location.assign('/login');
                return;
            }
            if (!syncResponse.ok) {
                throw new Error(`Live sync returned HTTP ${syncResponse.status}`);
            }
            const nextRevision = (await syncResponse.json()).data.revision;
            if (!force && nextRevision === revision) {
                setStatus('Sinkron otomatis aktif');
                return;
            }
            const refreshUrl = new URL(window.location.href);
            refreshUrl.searchParams.set('_live_sync', Date.now().toString());
            const response = await fetch(refreshUrl, {
                ...options,
                headers: { Accept: 'text/html' },
            });
            if (!response.ok) {
                throw new Error(`Live data returned HTTP ${response.status}`);
            }
            if (new URL(response.url).pathname === '/login') {
                stopped = true;
                window.location.assign(response.url);
                return;
            }
            const refreshedDocument = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (stopped || document.hidden || isEditing()) {
                return;
            }
            const replacements = targets.map((target) => [target, refreshedDocument.querySelector(`[data-live-sync="${target.dataset.liveSync}"]`)]);
            if (replacements.some(([, replacement]) => !replacement)) {
                throw new Error('Live sync response is missing a section');
            }
            replacements.forEach(([target, replacement]) => {
                if (target.innerHTML === replacement.innerHTML) {
                    return;
                }
                target.innerHTML = replacement.innerHTML;
                if (target.id) {
                    document.querySelector(`[data-search-target="${target.id}"]`)?.dispatchEvent(new Event('input', { bubbles: true }));
                }
                document.dispatchEvent(new CustomEvent('live-sync:updated', { detail: { key: target.dataset.liveSync } }));
            });
            revision = nextRevision;
            setStatus('Sinkron aktif · data diperbarui');
        } catch (error) {
            if (!stopped) {
                console.error('Gagal menyinkronkan data terbaru.', error);
                setStatus('Sinkron tertunda · mencoba lagi');
            }
        } finally {
            window.clearTimeout(timeout);
            refreshing = false;
        }
    };
    const onClick = (event) => {
        if (event.target instanceof Element && event.target.closest('[data-live-sync-refresh]')) {
            event.preventDefault();
            void check(true);
        }
    };
    const onResume = () => { void check(); };
    document.addEventListener('click', onClick);
    document.addEventListener('visibilitychange', onResume);
    window.addEventListener('online', onResume);
    window.addEventListener('focus', onResume);
    const timer = window.setInterval(onResume, interval);
    setStatus('Sinkron otomatis aktif');
    void check();
    return () => {
        stopped = true;
        controller?.abort();
        window.clearInterval(timer);
        document.removeEventListener('click', onClick);
        document.removeEventListener('visibilitychange', onResume);
        window.removeEventListener('online', onResume);
        window.removeEventListener('focus', onResume);
    };
}
