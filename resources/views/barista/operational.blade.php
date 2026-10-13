@extends('layouts.mobile-shell')

@section('content')
    <div class="page barista-page">
        <header class="barista-topbar">
            <h1>Barista - Operasional</h1>
            <nav aria-label="Aksi Barista" style="display:flex; gap:4px;">
                <a href="{{ route('barista.menu') }}" class="icon-btn" aria-label="Menu aktif">▤</a>
                <button type="button" class="icon-btn" data-modal-target="bar-transaction-modal" aria-label="Catat transaksi">⊞</button>
                <a href="{{ route('barista.operational', ['period' => $period]) }}" class="icon-btn" aria-label="Muat ulang">↻</a>
                <a href="{{ route('logout') }}" class="icon-btn" aria-label="Keluar">⇥</a>
            </nav>
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

        <section class="barista-financial-summary">
            <div class="barista-section-heading">
                <h2>Ringkasan Keuangan</h2>
                <form method="GET" action="{{ route('barista.operational') }}">
                    <label class="period-select">
                        <span>Periode</span>
                        <select name="period" onchange="this.form.submit()">
                            <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari ini</option>
                            <option value="7days" {{ $period === '7days' ? 'selected' : '' }}>7 hari</option>
                            <option value="30days" {{ $period === '30days' ? 'selected' : '' }}>30 hari</option>
                        </select>
                    </label>
                </form>
            </div>

            <div class="barista-summary-grid" data-live-sync="barista-financial-summary">
                <div class="barista-summary-card">
                    <span>Pemasukan</span>
                    <strong class="barista-income">Rp {{ number_format($income, 0, ',', '.') }}</strong>
                </div>
                <div class="barista-summary-card">
                    <span>Pengeluaran</span>
                    <strong class="barista-expense">Rp {{ number_format($expenses, 0, ',', '.') }}</strong>
                </div>
                <div class="barista-summary-card">
                    <span>Laba</span>
                    <strong class="barista-profit">Rp {{ number_format($profit, 0, ',', '.') }}</strong>
                </div>
            </div>
        </section>

        <section class="barista-section">
            <h2>Laporan Keuangan</h2>
            <h3>Transaksi {{ $period === 'today' ? 'hari ini' : ($period === '7days' ? '7 hari terakhir' : '30 hari terakhir') }}</h3>
            <div class="search-box">
                <span>⌕</span>
                <input type="text" placeholder="Cari transaksi" aria-label="Cari transaksi" data-search-target="barista-transactions">
            </div>
            <div id="barista-transactions" class="menu-list" data-live-sync="barista-transactions">
                @forelse ($transactions as $transaction)
                    <article class="barista-transaction-row" data-search-item>
                        <div>
                            <strong>{{ $transaction->description }}</strong>
                            <span>{{ $transaction->category }} · {{ $transaction->type === 'income' ? 'Pemasukan' : 'Pengeluaran' }}</span>
                            <small>{{ date('d/m/Y H:i', strtotime($transaction->occurred_at)) }}{{ $transaction->notes ? ' · '.$transaction->notes : '' }}</small>
                        </div>
                        <strong class="{{ $transaction->type === 'income' ? 'barista-income' : 'barista-expense' }}">
                            {{ $transaction->type === 'income' ? '+' : '−' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                        </strong>
                    </article>
                @empty
                    <div class="barista-empty-state">Belum ada transaksi pada periode ini</div>
                @endforelse
            </div>
        </section>

        <section class="barista-section">
            <h2>Pesanan Masuk (<span data-live-sync="barista-incoming-order-count">{{ $incomingOrders->count() }}</span>)</h2>
            <div class="search-box">
                <span>⌕</span>
                <input type="text" placeholder="Cari pesanan masuk" aria-label="Cari pesanan masuk" data-search-target="barista-incoming-orders">
            </div>
            <div id="barista-incoming-orders" class="menu-list" data-live-sync="barista-incoming-orders">
                @forelse ($incomingOrders as $order)
                    <article class="barista-incoming-order" data-search-item>
                        <span class="incoming-order-icon">♧</span>
                        <div>
                            <strong>Order #{{ $order->id }}</strong>
                            <span>{{ $order->customer_name ?: 'Pelanggan' }} · {{ str_replace('_', ' ', $order->status) }}</span>
                            <small>Masuk: {{ date('d/m/Y H:i', strtotime($order->created_at)) }} · Diubah: {{ date('d/m/Y H:i', strtotime($order->updated_at)) }}</small>
                        </div>
                    </article>
                @empty
                    <div class="barista-empty-state">Belum ada pesanan masuk</div>
                @endforelse
            </div>
        </section>

        <section class="barista-section">
            <h2>Daftar Menu Aktif</h2>
            <div class="search-box">
                <span>⌕</span>
                <input type="text" placeholder="Cari menu aktif" aria-label="Cari menu aktif" data-search-target="barista-operational-menu-list">
            </div>
            <div id="barista-operational-menu-list" class="menu-list barista-catalog-list" data-live-sync="barista-operational-menu-list">
                @forelse ($menus as $menu)
                    <article class="barista-catalog-card" data-search-item>
                        <div class="waiter-photo">
                            @if ($menu->image_path)
                                <img src="{{ asset($menu->image_path) }}" alt="{{ $menu->name }}">
                            @else
                                ☕
                            @endif
                        </div>
                        <div class="barista-catalog-info">
                            <strong>{{ $menu->name }}</strong>
                            <span>{{ $menu->description ?: $menu->category_name }}</span>
                            <small>{{ $menu->category_name }}</small>
                        </div>
                        <strong class="barista-menu-price">Rp{{ number_format($menu->price, 0, ',', '.') }}</strong>
                    </article>
                @empty
                    <div class="barista-empty-state">Belum ada menu aktif.</div>
                @endforelse
            </div>
        </section>

        <section class="barista-section">
            <h2>Transaksi Terbaru</h2>
            <div data-live-sync="barista-recent-transactions">
                @forelse ($transactions->take(5) as $transaction)
                    <div class="barista-recent-row">
                        <span>{{ $transaction->description }} · {{ $transaction->category }}</span>
                        <strong class="{{ $transaction->type === 'income' ? 'barista-income' : 'barista-expense' }}">Rp {{ number_format($transaction->amount, 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <div class="barista-empty-state">Belum ada transaksi terbaru</div>
                @endforelse
            </div>
        </section>

        <div id="bar-transaction-modal" class="modal-overlay">
            <div class="sheet barista-transaction-sheet">
                <h2 class="sheet-title">Catat Transaksi Bar</h2>
                <form method="POST" action="{{ route('barista.transactions.store') }}" class="sheet-form">
                    @csrf
                    <label class="barista-form-field">
                        <span>Jenis transaksi</span>
                        <select name="type" required>
                            <option value="expense">Pengeluaran</option>
                            <option value="income">Pemasukan</option>
                        </select>
                    </label>
                    <input name="description" type="text" placeholder="Nama transaksi" required>
                    <label class="barista-form-field">
                        <span>Kategori</span>
                        <select name="category" required>
                            <option value="Operasional">Operasional</option>
                            <option value="Bahan Baku">Bahan Baku</option>
                            <option value="Peralatan">Peralatan</option>
                            <option value="Penjualan">Penjualan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </label>
                    <input name="amount" type="number" min="1" step="1" placeholder="Nominal transaksi" required>
                    <textarea name="notes" placeholder="Catatan (opsional)" rows="3" style="width:100%; border:1px solid var(--line); border-radius:10px; padding:14px; font:inherit; resize:vertical;"></textarea>
                    <div class="sheet-actions">
                        <button type="button" class="action-link" data-close-modal="bar-transaction-modal">Batal</button>
                        <button type="submit" class="action-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-modal-target]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById(button.dataset.modalTarget)?.classList.add('show');
                });
            });

            document.querySelectorAll('[data-close-modal]').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.getElementById(button.dataset.closeModal)?.classList.remove('show');
                });
            });

            document.querySelectorAll('.modal-overlay').forEach(function (modal) {
                modal.addEventListener('click', function (event) {
                    if (event.target === modal) {
                        modal.classList.remove('show');
                    }
                });
            });
        });
    </script>
@endsection
