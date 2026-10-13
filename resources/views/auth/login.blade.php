@extends('layouts.mobile-shell')

@section('body-class', 'auth-screen')

@section('content')
    <main class="auth-stage">
        <section class="auth-intro" aria-label="Gotham City Coffee">
            <img class="auth-logo" src="{{ asset('uploads/menu/Logo.png') }}" alt="Logo Gotham City Coffee" width="112" height="112">
            <p class="auth-eyebrow">Gotham City Coffee</p>
            <h1>Diracik dengan hati.<br><em>Dikelola dengan rapi.</em></h1>
            <p class="auth-description">Satu tempat untuk menjaga setiap pesanan dan pelayanan tetap istimewa.</p>
            <span class="auth-brand-line" aria-hidden="true"></span>
        </section>

        <section class="auth-panel" aria-labelledby="login-heading">
            <div class="auth-panel-heading">
                <span class="auth-panel-eyebrow">Selamat datang kembali</span>
                <h2 id="login-heading">Masuk ke akun Anda</h2>
                <p>Kelola hari Anda di Gotham City Coffee.</p>
            </div>

            @if ($errors->any())
                <div class="auth-alert" role="alert">{{ $errors->first() }}</div>
            @endif

            <form class="auth-form" method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="auth-field">
                    <label for="email">Email</label>
                    <div class="auth-input">
                        <span class="auth-input-icon" aria-hidden="true">@</span>
                        <input id="email" name="email" type="text" inputmode="email" value="{{ old('email') }}" autocomplete="username" autocapitalize="none" spellcheck="false" placeholder="Masukkan email Anda" required>
                    </div>
                </div>

                <div class="auth-field">
                    <label for="password">Password</label>
                    <div class="auth-input">
                        <svg class="auth-input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/><path d="M12 14v3"/></svg>
                        <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password Anda" required>
                        <button class="auth-password-toggle" type="button" data-password-toggle aria-controls="password" aria-label="Tampilkan password" aria-pressed="false">
                            <svg class="auth-eye-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="auth-eye-closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="m3 3 18 18M10.5 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3.3 4.3M6.2 6.2A19 19 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.5-1.4M10 10a3 3 0 0 0 4 4"/></svg>
                        </button>
                    </div>
                </div>

                <button class="auth-submit" type="submit">
                    <span>Masuk ke Dashboard</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
                </button>
            </form>
            <div class="auth-panel-footer">Gotham City Coffee <span aria-hidden="true">&middot;</span> Staff Portal</div>
        </section>
    </main>
@endsection
