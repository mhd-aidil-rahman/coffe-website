@extends('layouts.mobile-shell')

@section('content')
    <div class="page barista-page">
        <header class="barista-topbar">
            <a href="{{ route('barista.operational') }}" class="icon-btn" aria-label="Kembali">←</a>
            <h1>Menu Management</h1>
            <a href="{{ route('barista.operational') }}" class="icon-btn" aria-label="Operasional">↩</a>
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

        <h2 class="barista-section-title">Menu Management</h2>
        <p class="barista-section-description">Barista dan owner dapat mengatur ketersediaan menu.</p>
        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari menu" aria-label="Cari menu" data-search-target="barista-menu-list">
        </div>

        <div id="barista-menu-list" class="menu-list barista-catalog-list" data-live-sync="barista-menu-list">
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
                        <small class="barista-menu-status {{ $menu->active ? 'active' : 'inactive' }}">
                            {{ $menu->active ? 'Aktif · tersedia untuk pesanan' : 'Nonaktif · tidak tersedia untuk pesanan' }}
                        </small>
                        <form method="POST" action="{{ route('barista.menu.status', $menu->id) }}">
                            @csrf
                            <input type="hidden" name="active" value="{{ $menu->active ? 0 : 1 }}">
                            <button type="submit" class="barista-menu-toggle {{ $menu->active ? 'deactivate' : 'activate' }}">
                                {{ $menu->active ? 'Matikan menu' : 'Aktifkan menu' }}
                            </button>
                        </form>
                    </div>
                    <strong class="barista-menu-price">Rp{{ number_format($menu->price, 0, ',', '.') }}</strong>
                </article>
            @empty
                <div class="barista-empty-state">Belum ada menu dari akun owner.</div>
            @endforelse
        </div>
    </div>
@endsection
