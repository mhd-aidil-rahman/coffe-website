@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header" style="padding-top:0;">
            <a href="{{ route('owner.dashboard') }}" style="font-size:34px; color:#1d1d1d; text-decoration:none;">←</a>
            <h1 style="font-size:26px; margin:0;">Owner - Laporan &amp; Inventaris</h1>
            <button class="icon-btn" type="button" aria-label="add">＋</button>
        </div>

        <div class="list-header" style="margin-top:10px;">Laporan Keuangan Harian</div>
        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari laporan" aria-label="Cari laporan" data-search-target="daily-reports">
        </div>

        <div id="daily-reports">
            <div class="summary-box" data-search-item style="margin-top:12px;">
                <div style="font-size:18px; font-weight:700;">2026-10-06</div>
                <div style="margin-top:6px; color:#4d5653;">Penjualan Rp279000 | Biaya Rp100000</div>
            </div>
        </div>

        <div class="list-header" style="margin-top:16px;">Inventaris</div>
        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari inventaris" aria-label="Cari inventaris" data-search-target="inventory-items">
        </div>

        @php
            $items = [
                ['name' => 'Biji Kopi Arabika', 'stock' => 'Stok 3000.0 gram', 'min' => 'Minimum 1000.0', 'icon' => '📦'],
                ['name' => 'Gula Aren', 'stock' => 'Stok 2000.0 gram', 'min' => 'Minimum 500.0', 'icon' => '📦'],
                ['name' => 'Susu Fresh', 'stock' => 'Stok 5000.0 ml', 'min' => 'Minimum 1500.0', 'icon' => '📦'],
            ];
        @endphp

        <div id="inventory-items" style="margin-top:14px; display:flex; flex-direction:column; gap:12px;">
            @foreach ($items as $item)
            <div class="category-row" data-search-item style="padding:12px 14px; align-items:center; min-height: 78px;">
                    <div style="display:flex; align-items:center; gap:12px;">
                        <div style="width:42px; height:42px; border-radius:10px; background:#dfeae5; display:grid; place-items:center; font-size:22px;">{{ $item['icon'] }}</div>
                        <div>
                            <div style="font-size:18px; font-weight:700;">{{ $item['name'] }}</div>
                            <div style="font-size:13px; color:#585d59; margin-top:2px;">{{ $item['stock'] }} | {{ $item['min'] }}</div>
                        </div>
                    </div>
                    <div style="font-size:24px; color:#2a2a2a;">✎</div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
