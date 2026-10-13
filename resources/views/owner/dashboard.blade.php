@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header">
            <h1>Owner Dashboard</h1>
            <div style="display:flex; gap:8px; align-items:center;">
                <button class="icon-btn" type="button" aria-label="refresh" data-live-sync-refresh>↻</button>
                <a href="{{ route('logout') }}" class="icon-btn" aria-label="logout" style="text-decoration:none;">⎋</a>
            </div>
        </div>

        <div class="live-sync-status" data-live-sync-status role="status" aria-live="polite"></div>

        <div class="metrics-grid" data-live-sync="owner-metrics">
            <div class="metric-card total">
                <div class="row"><span class="bullet">🛒</span> Total Pesanan</div>
                <div class="value"><span>{{ $metrics['total_orders'] }}</span></div>
            </div>

            <div class="metric-card income">
                <div class="row"><span class="bullet">💰</span> Pendapatan</div>
                <div class="value"><span>Rp {{ number_format($metrics['income'], 0, ',', '.') }}</span></div>
            </div>

            <div class="metric-card active">
                <div class="row"><span class="bullet">🧾</span> Order Aktif</div>
                <div class="value"><span>{{ $metrics['active_orders'] }}</span></div>
            </div>

            <div class="metric-card pending">
                <div class="row"><span class="bullet">✕</span> Pembatalan Pending</div>
                <div class="value"><span>{{ $metrics['pending_cancellations'] }}</span></div>
            </div>
        </div>

        <h2 class="section-title">Pesanan Terbaru</h2>
        <div class="menu-list" data-live-sync="owner-recent-orders">
            @forelse ($recentOrders as $order)
                <div class="summary-box" style="margin:0;">
                    <div style="display:flex; justify-content:space-between; gap:12px;">
                        <strong>Order #{{ $order->id }} · {{ $order->customer_name ?: 'Pelanggan' }}</strong>
                        <strong>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</strong>
                    </div>
                    <div class="menu-meta">{{ $order->waiter_name }} · {{ str_replace('_', ' ', $order->status) }} · {{ $order->created_at }}</div>
                </div>
            @empty
                <div class="summary-box" style="margin:0;">Belum ada pesanan.</div>
            @endforelse
        </div>

        <h2 class="section-title">Semua Menu</h2>

        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari menu" aria-label="Cari menu" data-search-target="dashboard-menu-list">
        </div>

        <div class="menu-list" id="dashboard-menu-list" data-live-sync="owner-menu-list">
            @forelse ($menus as $menu)
                <div class="menu-item" data-search-item>
                    <div class="menu-no">{{ $loop->iteration }}</div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div class="thumb">
                            @if ($menu->image_path)
                                <img src="{{ asset($menu->image_path) }}" alt="{{ $menu->name }}">
                            @else
                                ☕
                            @endif
                        </div>
                        <div>
                            <p class="menu-name">{{ $menu->name }}</p>
                            <div class="menu-meta">{{ $menu->category_name }} · {{ $menu->active ? 'Aktif' : 'Nonaktif' }}</div>
                        </div>
                    </div>
                    <div class="unit-badge">Rp {{ number_format($menu->price, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="summary-box">Belum ada menu.</div>
            @endforelse
        </div>

        <div class="quick-grid">
            <a href="{{ route('owner.menu') }}" style="text-decoration:none; color:inherit;">
                <div class="quick-card">
                    <div class="icon-large">📋</div>
                    <div>Kelola Menu</div>
                </div>
            </a>

            <a href="{{ route('owner.staff') }}" style="text-decoration:none; color:inherit;">
                <div class="quick-card">
                    <div class="icon-large">👥</div>
                    <div>Kelola Staff</div>
                </div>
            </a>

            <a href="{{ route('owner.laporan') }}" style="text-decoration:none; color:inherit;">
                <div class="quick-card">
                    <div class="icon-large">💹</div>
                    <div>Laporan Keuangan</div>
                </div>
            </a>

            <a href="{{ route('owner.inventory') }}" style="text-decoration:none; color:inherit;">
                <div class="quick-card">
                    <div class="icon-large">📦</div>
                    <div>Inventaris &amp; Laporan</div>
                </div>
            </a>

            <a href="{{ route('owner.operational') }}" style="text-decoration:none; color:inherit; grid-column: 1 / -1;">
                <div class="quick-card">
                    <div class="icon-large">🧾</div>
                    <div>Permintaan Pembatalan</div>
                </div>
            </a>
        </div>
    </div>
@endsection
