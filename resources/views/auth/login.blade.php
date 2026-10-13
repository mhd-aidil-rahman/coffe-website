@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="login-card">
            <div class="brand-mark">
                <img src="{{ asset('uploads/menu/Logo.png') }}" alt="Gotham City Coffee logo">
            </div>
            <h1 class="brand-text">Gotham City Coffee</h1>
            <div class="brand-sub">Coffee, craft, and control.</div>

            @if ($errors->any())
                <div style="padding: 10px 12px; margin-bottom: 12px; border-radius: 8px; background: rgba(212,73,61,0.1); color: #a33a36; font-size: 13px; border: 1px solid rgba(212,73,61,0.2);">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <div class="input-wrap">
                        <span class="icon">@</span>
                        <input id="email" name="email" type="email" value="owner@gothamcoffee.id" autocomplete="email" required>
                    </div>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <span class="icon">🔒</span>
                        <input id="password" name="password" type="password" value="password123" autocomplete="current-password" required>
                    </div>
                </div>

                <button class="btn-primary" type="submit">→ Masuk ke Dashboard</button>
            </form>

            <div class="login-help">
                <div>Akun owner: <strong>owner@gothamcoffee.id</strong></div>
                <div>Password owner: <strong>password123</strong></div>
                <div>Akun Waiter dan Barista dibuat owner melalui Kelola Staff.</div>
            </div>
        </div>
    </div>
@endsection
