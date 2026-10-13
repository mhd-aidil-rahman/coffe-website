@extends('layouts.mobile-shell')

@section('content')
    <div class="page waiter-page">
        <header class="waiter-topbar">
            <h1>Waiter - Order Taking</h1>
            <div style="display:flex; gap:8px;">
                <a href="{{ route('waiter.orders') }}" class="icon-btn" aria-label="Refresh">↻</a>
                <a href="{{ route('logout') }}" class="icon-btn" aria-label="Keluar">⇥</a>
            </div>
        </header>

        <div class="live-sync-status" data-live-sync-status role="status" aria-live="polite"></div>

        @if (session('success'))
            <div role="status" class="summary-box" style="color:var(--green);">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div role="alert" class="summary-box" style="color:var(--red);">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <h2 class="list-header">Buat Pesanan</h2>
        <form id="create-order-form" method="POST" action="{{ route('waiter.orders.store') }}">
            @csrf
            <div class="search-box waiter-customer-field">
                <span>♙</span>
                <input type="text" name="customer_name" placeholder="Nama pelanggan (opsional)" aria-label="Nama pelanggan">
            </div>

            <div class="search-box" style="margin-top:12px;">
                <span>⌕</span>
                <input type="text" placeholder="Cari menu" aria-label="Cari menu" data-search-target="waiter-menu-list">
            </div>

            <div id="waiter-menu-list" class="menu-list waiter-menu-list" data-live-sync="waiter-menu-list">
                @forelse ($menus as $menu)
                    <article class="waiter-menu-card" data-search-item data-menu-id="{{ $menu->id }}" data-menu-price="{{ $menu->price }}">
                        <div class="waiter-photo">
                            @if ($menu->image_path)
                                <img src="{{ asset($menu->image_path) }}" alt="{{ $menu->name }}">
                            @else
                                ☕
                            @endif
                        </div>
                        <div class="waiter-menu-info">
                            <strong>{{ $menu->name }}</strong>
                            <span>{{ $menu->category_name }}</span>
                            <b>Rp {{ number_format($menu->price, 0, ',', '.') }}</b>
                        </div>
                        <div class="quantity-controls">
                            <button type="button" class="quantity-button" data-quantity-change="-1" aria-label="Kurangi {{ $menu->name }}">−</button>
                            <span data-quantity-value>0</span>
                            <button type="button" class="quantity-button" data-quantity-change="1" aria-label="Tambah {{ $menu->name }}">+</button>
                        </div>
                    </article>
                @empty
                    <div class="summary-box">Belum ada menu aktif.</div>
                @endforelse
            </div>

            <div id="order-item-inputs"></div>
            <button id="submit-order-button" class="btn-primary waiter-submit" type="submit" disabled>Buat Pesanan - Rp 0</button>
        </form>

        <section class="waiter-active-orders">
            <h2 class="list-header">Pesanan Aktif</h2>
            <div class="search-box">
                <span>⌕</span>
                <input type="text" placeholder="Cari pesanan" aria-label="Cari pesanan" data-search-target="waiter-active-orders-list">
            </div>

            <div id="waiter-active-orders-list" class="menu-list" data-live-sync="waiter-active-orders">
                @forelse ($orders as $order)
                    <article class="waiter-order-card" data-search-item>
                        <div class="waiter-order-heading">
                            <strong>Order #{{ $order->id }}{{ $order->customer_name ? ' · '.$order->customer_name : '' }}</strong>
                            <div class="menu-more-wrap">
                                <button type="button" class="more-btn waiter-order-more" data-order-id="{{ $order->id }}" aria-label="Aksi order {{ $order->id }}">⋮</button>
                                <div id="waiter-order-actions-{{ $order->id }}" class="menu-action-menu" style="display:none;">
                                    @if ($order->status === 'sent_to_kitchen')
                                        <button type="button" class="menu-action-item" data-open-payment="{{ $order->id }}">Selesaikan &amp; Bayar</button>
                                        <form method="POST" action="{{ route('waiter.orders.cancel', $order->id) }}" onsubmit="return confirm('Ajukan pembatalan order ini ke owner?')">
                                            @csrf
                                            <button type="submit" class="menu-action-item danger">Ajukan pembatalan</button>
                                        </form>
                                    @else
                                        <span class="menu-action-item">
                                            @if ($order->void_code_issued)
                                                Disetujui owner
                                            @elseif ($order->void_code_approved)
                                                Kode Void kedaluwarsa
                                            @else
                                                Menunggu keputusan owner
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="waiter-order-meta">
                            @foreach ($orderItems->get($order->id, collect()) as $orderItem)
                                <div>{{ $orderItem->quantity }} item · {{ $orderItem->menu_name }}</div>
                            @endforeach
                            <div>Status: {{ str_replace('_', ' ', $order->status) }}</div>
                            @if ($order->cancellation_reason)
                                <div>Alasan: {{ $order->cancellation_reason }}</div>
                            @endif
                            <div>Dibuat: {{ date('d/m/Y H:i', strtotime($order->created_at)) }}</div>
                            @if ($order->status === 'cancellation_pending')
                                @if ($order->void_code_issued)
                                    <form method="POST" action="{{ route('waiter.orders.void', $order->id) }}" style="display:flex; gap:8px; margin-top:10px;">
                                        @csrf
                                        <input
                                            type="text"
                                            name="void_code"
                                            inputmode="numeric"
                                            pattern="[0-9]{6}"
                                            maxlength="6"
                                            autocomplete="off"
                                            placeholder="Masukkan Kode Void"
                                            aria-label="Kode Void Order {{ $order->id }}"
                                            required
                                            style="min-width:0; flex:1; border:1px solid var(--line); border-radius:10px; padding:10px;"
                                        >
                                        <button type="submit" class="action-primary">Batalkan</button>
                                    </form>
                                @elseif ($order->void_code_approved)
                                    <div>Kode Void kedaluwarsa. Hubungi owner untuk menerbitkan kode baru.</div>
                                @else
                                    <div>Menunggu persetujuan owner untuk pembatalan.</div>
                                @endif
                            @endif
                        </div>
                        <strong class="waiter-order-total">Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
                    </article>

                    @if ($order->status === 'sent_to_kitchen')
                        <div id="payment-modal-{{ $order->id }}" class="modal-overlay">
                            <div class="sheet small">
                                <h2 class="sheet-title">Selesaikan dan Catat Pembayaran</h2>
                                <p style="text-align:center;">Order #{{ $order->id }} · Rp {{ number_format($order->total_amount, 0, ',', '.') }}</p>
                                <form method="POST" action="{{ route('waiter.orders.pay', $order->id) }}" class="sheet-form">
                                    @csrf
                                    <button class="payment-option" type="submit" name="payment_method" value="cash">💵 Tunai</button>
                                    <button class="payment-option" type="submit" name="payment_method" value="qris">▦ QRIS</button>
                                    <button class="payment-option" type="submit" name="payment_method" value="card">▤ Kartu Debit/Kredit</button>
                                    <button class="action-link" type="button" data-close-modal="payment-modal-{{ $order->id }}">Batal</button>
                                </form>
                            </div>
                        </div>
                    @endif
                @empty
                    <div class="summary-box">Belum ada pesanan aktif.</div>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const createOrderForm = document.getElementById('create-order-form');
            const itemInputs = document.getElementById('order-item-inputs');
            const submitOrderButton = document.getElementById('submit-order-button');
            const quantities = new Map();

            function updateCart() {
                let total = 0;
                let itemCount = 0;
                itemInputs.replaceChildren();

                document.querySelectorAll('.waiter-menu-card').forEach(function (card) {
                    const id = card.dataset.menuId;
                    const quantity = quantities.get(id) || 0;
                    const price = Number(card.dataset.menuPrice);
                    card.querySelector('[data-quantity-value]').textContent = quantity;

                    if (quantity > 0) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'items[' + id + ']';
                        input.value = quantity;
                        itemInputs.append(input);
                        total += price * quantity;
                        itemCount += quantity;
                    }
                });

                submitOrderButton.disabled = itemCount === 0;
                submitOrderButton.textContent = 'Buat Pesanan - Rp ' + new Intl.NumberFormat('id-ID').format(total);
            }

            createOrderForm.addEventListener('submit', function (event) {
                if (itemInputs.children.length === 0) {
                    event.preventDefault();
                }
            });

            document.addEventListener('click', function (event) {
                const clickTarget = event.target instanceof Element ? event.target : null;

                if (! clickTarget) {
                    return;
                }

                const quantityButton = clickTarget.closest('[data-quantity-change]');
                if (quantityButton) {
                    const card = quantityButton.closest('.waiter-menu-card');
                    const id = card.dataset.menuId;
                    const quantity = Math.max(0, Math.min(99, (quantities.get(id) || 0) + Number(quantityButton.dataset.quantityChange)));
                    quantities.set(id, quantity);
                    updateCart();

                    return;
                }

                const orderMoreButton = clickTarget.closest('.waiter-order-more');
                if (orderMoreButton) {
                    const menu = document.getElementById('waiter-order-actions-' + orderMoreButton.dataset.orderId);
                    document.querySelectorAll('.menu-action-menu').forEach(function (actionMenu) {
                        if (actionMenu !== menu) {
                            actionMenu.style.display = 'none';
                        }
                    });
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';

                    return;
                }

                const paymentButton = clickTarget.closest('[data-open-payment]');
                if (paymentButton) {
                    document.getElementById('waiter-order-actions-' + paymentButton.dataset.openPayment).style.display = 'none';
                    document.getElementById('payment-modal-' + paymentButton.dataset.openPayment).classList.add('show');

                    return;
                }

                const closeButton = clickTarget.closest('[data-close-modal]');
                if (closeButton) {
                    document.getElementById(closeButton.dataset.closeModal).classList.remove('show');

                    return;
                }

                const modal = clickTarget.classList.contains('modal-overlay') ? clickTarget : null;
                if (modal) {
                    modal.classList.remove('show');
                }

                document.querySelectorAll('.menu-action-menu').forEach(function (menu) {
                    menu.style.display = 'none';
                });
            });

            document.addEventListener('live-sync:updated', function (event) {
                if (['waiter-menu-list', 'waiter-active-orders'].includes(event.detail.key)) {
                    updateCart();
                }
            });

            updateCart();
        });
    </script>
@endsection
