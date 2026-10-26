import { startLiveSync } from './live-sync';

const notificationRoot = document.querySelector('[data-order-completion-notifications]');
const notificationToast = document.getElementById('order-completion-toast');

if (notificationRoot && notificationToast) {
    const cursorStorageKey = `gotham-order-completion-cursor:${notificationRoot.dataset.accountKey}`;
    let cursor;

    try {
        const savedCursor = JSON.parse(localStorage.getItem(cursorStorageKey) || 'null');
        cursor = savedCursor || {
            paidAt: notificationRoot.dataset.initialPaidAt,
            orderId: Number(notificationRoot.dataset.initialOrderId || 0),
        };

        if (!savedCursor) {
            localStorage.setItem(cursorStorageKey, JSON.stringify(cursor));
        }
    } catch (error) {
        console.error('Gagal membaca posisi notifikasi transaksi.', error);
        cursor = {
            paidAt: notificationRoot.dataset.initialPaidAt,
            orderId: Number(notificationRoot.dataset.initialOrderId || 0),
        };
    }

    const notificationQueue = [];
    let isCheckingNotifications = false;
    let toastTimer;

    const displayNextNotification = () => {
        if (notificationQueue.length === 0) {
            notificationToast.hidden = true;

            return;
        }

        const event = notificationQueue.shift();
        const formattedTotal = new Intl.NumberFormat('id-ID').format(event.total_amount);
        notificationToast.textContent = `Transaksi selesai · Order #${event.id} · ${event.customer_name} · Rp ${formattedTotal}`;
        notificationToast.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(displayNextNotification, 7000);
    };

    const checkCompletedOrders = async () => {
        if (isCheckingNotifications) {
            return;
        }

        isCheckingNotifications = true;

        try {
            const notificationUrl = new URL('/notifications/completed-orders', window.location.origin);
            notificationUrl.searchParams.set('after_paid_at', cursor.paidAt);
            notificationUrl.searchParams.set('after_order_id', cursor.orderId.toString());

            const response = await fetch(notificationUrl, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
            });

            if (response.status === 401) {
                return;
            }

            if (!response.ok) {
                throw new Error(`Order notification returned HTTP ${response.status}`);
            }

            const { events } = await response.json();

            events.forEach((event) => {
                notificationQueue.push(event);
                cursor = { paidAt: event.paid_at, orderId: event.id };
            });

            if (events.length > 0) {
                localStorage.setItem(cursorStorageKey, JSON.stringify(cursor));
                if (notificationToast.hidden) {
                    displayNextNotification();
                }
            }
        } catch (error) {
            console.error('Gagal memeriksa notifikasi transaksi selesai.', error);
        } finally {
            isCheckingNotifications = false;
        }
    };

    checkCompletedOrders();
    window.setInterval(checkCompletedOrders, 5000);
}

startLiveSync();

const passwordToggle = document.querySelector('[data-password-toggle]');
if (passwordToggle) {
    const passwordInput = document.getElementById(passwordToggle.getAttribute('aria-controls'));
    if (passwordInput) {
        passwordToggle.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'password';
            passwordInput.type = isVisible ? 'text' : 'password';
            passwordToggle.setAttribute('aria-pressed', String(isVisible));
            passwordToggle.setAttribute('aria-label', isVisible ? 'Sembunyikan password' : 'Tampilkan password');
        });
    }
}
