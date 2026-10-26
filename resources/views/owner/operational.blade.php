@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header" style="padding-top:0;">
            <a href="{{ route('owner.dashboard') }}" style="font-size:34px; color:#1d1d1d; text-decoration:none;">←</a>
            <h1 style="font-size:26px; margin:0;">Owner - Operational</h1>
            <div style="width:24px;"></div>
        </div>

        @if (session('success'))
            <div role="status" class="summary-box" style="color:var(--green);">{{ session('success') }}</div>
        @endif

        @if (session('void_code'))
            <div role="alert" class="summary-box" style="border:2px solid var(--green);">
                <div>Kode Void untuk Order #{{ session('void_code_order_id') }}</div>
                <strong style="display:block; font-size:30px; letter-spacing:8px; margin:8px 0;">{{ session('void_code') }}</strong>
                <div>Sampaikan kode ini kepada waiter. Kode hanya berlaku 15 menit dan hanya dapat digunakan sekali.</div>
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" class="summary-box" style="color:var(--red);">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="list-header" style="margin-top:8px;">Permintaan Pembatalan</div>
        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari pembatalan" aria-label="Cari pembatalan" data-search-target="cancellation-list">
        </div>

        <div id="cancellation-list" data-live-sync="owner-cancellation-orders" style="margin-top:14px;">
            @forelse ($cancellationOrders as $order)
                <div class="summary-box" data-search-item>
                    <div style="display:flex; justify-content:space-between; gap:12px; font-weight:700;">
                        <span>Order #{{ $order->id }} · {{ $order->customer_name ?: 'Pelanggan' }}</span>
                        <span>Rp {{ number_format($order->total_amount, 0, ',', '.') }}</span>
                    </div>
                    <div class="menu-meta">{{ $order->waiter_name }} · {{ $order->cancellation_reason ?: 'Tanpa alasan' }}</div>
                    @if ($order->void_code_hash && $order->void_code_expires_at)
                        <div class="menu-meta">
                            @if (now()->lt($order->void_code_expires_at))
                                Kode Void sudah diterbitkan. Menunggu waiter memasukkan kode.
                            @else
                                Kode Void kedaluwarsa. Terbitkan kode baru jika permintaan tetap disetujui.
                            @endif
                        </div>
                    @endif
                    <div style="display:flex; justify-content:flex-end; gap:16px; margin-top:12px;">
                        <form method="POST" action="{{ route('owner.orders.cancellation', $order->id) }}">
                            @csrf
                            <button type="submit" name="decision" value="reject" class="action-link">Tolak</button>
                        </form>
                        <form method="POST" action="{{ route('owner.orders.cancellation', $order->id) }}">
                            @csrf
                            <button type="submit" name="decision" value="approve" class="action-primary">
                                {{ $order->void_code_hash ? 'Terbitkan ulang Kode Void' : 'Setujui & buat Kode Void' }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="summary-box" data-search-item>
                    <div style="font-size:18px; color:#2e2e2e;">Tidak ada permintaan pending</div>
                </div>
            @endforelse
        </div>

        <div class="list-header" style="margin-top:20px;">Status Karyawan</div>

        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari karyawan" aria-label="Cari karyawan" data-search-target="owner-staff-list">
        </div>

        <div id="owner-staff-list" data-live-sync="owner-operational-staff" style="margin-top:16px; display:flex; flex-direction:column; gap:14px;">
            @forelse ($staffMembers as $staffMember)
                <div class="status-row" data-search-item style="padding:10px 0;">
                    <div class="status-meta">
                        <div class="status-name">{{ $staffMember->name }}</div>
                        <div class="status-email">{{ $staffMember->email }} · {{ $staffMember->position }}</div>
                    </div>
                    <form method="POST" action="{{ route('owner.staff.toggle', $staffMember->id) }}">
                        @csrf
                        <button type="submit" class="toggle {{ $staffMember->active ? 'on' : '' }}" aria-label="{{ $staffMember->active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $staffMember->name }}" style="border:none; cursor:pointer;"></button>
                    </form>
                </div>
            @empty
                <div class="summary-box">Belum ada data staff.</div>
            @endforelse
        </div>

        <a href="{{ route('owner.staff') }}" class="action-primary" style="display:inline-block; margin-top:16px; text-decoration:none;">Kelola semua staff</a>
    </div>
@endsection
