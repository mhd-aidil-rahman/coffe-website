@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header" style="padding-top:0;">
            <a href="{{ route('owner.dashboard') }}" style="font-size:34px; color:#1d1d1d; text-decoration:none;">←</a>
            <h1 style="font-size:26px; margin:0;">Laporan Keuangan</h1>
            <button class="icon-btn" type="button" aria-label="refresh" data-live-sync-refresh>↻</button>
        </div>

        <div class="live-sync-status" data-live-sync-status role="status" aria-live="polite"></div>

        @if ($errors->any())
            <div role="alert" class="summary-box" style="color:var(--red);">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="summary-box">
            <form method="GET" action="{{ route('owner.laporan') }}">
                <label style="display:block; font-size:14px; color:#4e4e4e; margin-bottom:8px;">Periode laporan</label>
                <select name="period" id="report-period" style="width:100%; height:42px; border-radius:10px; border:1px solid var(--line); background:rgba(255,255,255,0.18); padding:0 12px; font-size:16px;">
                    <option value="today" {{ $period === 'today' ? 'selected' : '' }}>Hari ini</option>
                    <option value="7days" {{ $period === '7days' ? 'selected' : '' }}>7 hari terakhir</option>
                    <option value="30days" {{ $period === '30days' ? 'selected' : '' }}>30 hari terakhir</option>
                    <option value="custom" {{ $period === 'custom' ? 'selected' : '' }}>Pilih periode sendiri</option>
                </select>
                <div id="custom-report-period" style="display:{{ $period === 'custom' ? 'grid' : 'none' }}; gap:10px; margin-top:10px;">
                    <label style="display:grid; gap:5px; font-size:14px;">
                        Dari tanggal
                        <input type="date" name="start_date" value="{{ $startDate }}" style="height:42px; border-radius:10px; border:1px solid var(--line); padding:0 12px;">
                    </label>
                    <label style="display:grid; gap:5px; font-size:14px;">
                        Sampai tanggal
                        <input type="date" name="end_date" value="{{ $endDate }}" style="height:42px; border-radius:10px; border:1px solid var(--line); padding:0 12px;">
                    </label>
                </div>
                <div style="display:flex; gap:8px; margin-top:12px;">
                    <button type="submit" class="action-link" style="flex:1; border:1px solid var(--line); border-radius:10px;">Tampilkan</button>
                    <button type="submit" name="export" value="xlsx" class="action-primary" style="flex:1;">Unduh Excel (.xlsx)</button>
                </div>
            </form>
        </div>
        <script>
            document.getElementById('report-period')?.addEventListener('change', function () {
                const customPeriod = document.getElementById('custom-report-period');

                if (customPeriod) {
                    customPeriod.style.display = this.value === 'custom' ? 'grid' : 'none';
                }
            });
        </script>

        <div class="summary-box">
            <div class="summary-grid" data-live-sync="owner-financial-summary">
                <div class="summary-card">
                    <div class="icon">↗</div>
                    <div class="label">Pemasukan</div>
                    <div class="amount">Rp {{ number_format($income, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card">
                    <div class="icon">↘</div>
                    <div class="label">Pengeluaran</div>
                    <div class="amount">Rp {{ number_format($expenses, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card">
                    <div class="icon">🧾</div>
                    <div class="label">Laba Bersih</div>
                    <div class="amount">Rp {{ number_format($profit, 0, ',', '.') }}</div>
                </div>
                <div class="summary-card">
                    <div class="icon">⟲</div>
                    <div class="label">Refund</div>
                    <div class="amount">Rp {{ number_format($refunds, 0, ',', '.') }}</div>
                </div>
            </div>
        </div>

        <div style="margin-top:22px; font-size:20px; font-weight:700;">Pemasukan per Metode Pembayaran</div>

        <div class="report-list" style="margin-top:12px;" data-live-sync="owner-payment-methods">
            @forelse ($paymentsByMethod as $payment)
                <div class="report-row">
                    <div>
                        <div style="font-weight:700;">{{ strtoupper($payment->payment_method) }}</div>
                        <small>{{ $payment->transaction_count }} transaksi</small>
                    </div>
                    <div class="label-info">Rp {{ number_format($payment->total_amount, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="report-row"><small>Belum ada pembayaran.</small></div>
            @endforelse
        </div>

        <div style="margin-top:22px; font-size:20px; font-weight:700;">Pengeluaran per Kategori</div>

        <div class="report-list" style="margin-top:12px;" data-live-sync="owner-expense-categories">
            @forelse ($expensesByCategory as $expense)
                <div class="report-row">
                    <div>
                        <div style="font-weight:700;">{{ $expense->category }}</div>
                        <small>{{ $expense->transaction_count }} transaksi bar</small>
                    </div>
                    <div class="label-negative">Rp {{ number_format($expense->total_amount, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="report-row"><small>Belum ada pengeluaran tercatat.</small><div class="label-negative">Rp 0</div></div>
            @endforelse
        </div>

        <div style="margin-top:22px; font-size:20px; font-weight:700;">Tren Harian</div>
        <div class="report-list" style="margin-top:12px;" data-live-sync="owner-daily-trends">
            @forelse ($dailyTrends as $trend)
                <div class="report-row">
                    <div>
                        <div style="font-weight:700;">{{ $trend->payment_date }}</div>
                        <small>Masuk Rp {{ number_format($trend->income_amount, 0, ',', '.') }} · Keluar Rp {{ number_format($trend->expense_amount, 0, ',', '.') }}</small>
                    </div>
                    <div class="{{ $trend->net_amount >= 0 ? 'label-positive' : 'label-negative' }}">Rp {{ number_format($trend->net_amount, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="report-row"><small>Belum ada tren pembayaran.</small></div>
            @endforelse
        </div>

        <div style="margin-top:22px; font-size:20px; font-weight:700;">Transaksi Terbaru</div>
        <div class="search-box" style="margin-top:10px;">
            <span>⌕</span>
            <input type="text" placeholder="Cari transaksi" aria-label="Cari transaksi" data-search-target="recent-transactions">
        </div>

        <div class="report-list" id="recent-transactions" style="margin-top:12px;" data-live-sync="owner-recent-transactions">
            @forelse ($recentTransactions as $transaction)
                <div class="report-row" data-search-item>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:34px; height:34px; border-radius:50%; background:#0d5b4d; color:white; display:grid; place-items:center; font-size:22px;">+</div>
                        <div>
                            <div style="font-weight:700;">Pembayaran Order #{{ $transaction->id }}{{ $transaction->customer_name ? ' · '.$transaction->customer_name : '' }}</div>
                            <small>{{ $transaction->waiter_name }} · {{ strtoupper($transaction->payment_method) }} · {{ $transaction->paid_at }}</small>
                        </div>
                    </div>
                    <div class="label-positive">+Rp {{ number_format($transaction->total_amount, 0, ',', '.') }}</div>
                </div>
            @empty
                <div class="report-row"><small>Belum ada transaksi.</small></div>
            @endforelse
        </div>

        <div style="margin-top:22px; font-size:20px; font-weight:700;">Catatan Transaksi Bar</div>
        <div class="report-list" style="margin-top:12px;" data-live-sync="owner-recent-bar-transactions">
            @forelse ($recentBarTransactions as $transaction)
                <div class="report-row">
                    <div>
                        <div style="font-weight:700;">{{ $transaction->description }}</div>
                        <small>{{ $transaction->staff_name ?: 'Staff' }} · {{ $transaction->category }} · {{ $transaction->occurred_at }}</small>
                    </div>
                    <div class="{{ $transaction->type === 'income' ? 'label-positive' : 'label-negative' }}">
                        {{ $transaction->type === 'income' ? '+' : '−' }}Rp {{ number_format($transaction->amount, 0, ',', '.') }}
                    </div>
                </div>
            @empty
                <div class="report-row"><small>Belum ada transaksi bar.</small></div>
            @endforelse
        </div>
    </div>
@endsection
