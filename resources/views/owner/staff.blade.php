@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header" style="padding-top:0;">
            <a href="{{ route('owner.dashboard') }}" style="font-size:34px; color:#1d1d1d; text-decoration:none;">←</a>
            <h1 style="font-size:26px; margin:0;">Kelola Staff</h1>
            <div style="width:24px;"></div>
        </div>

        @if (session('success'))
            <div style="margin:10px 0 14px; padding:10px 12px; border-radius:10px; background:rgba(13,91,77,0.08); color:#0d5b4d; border:1px solid rgba(13,91,77,0.16); font-size:13px; font-weight:600;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" style="margin:10px 0 14px; padding:10px 12px; border-radius:10px; background:#fff0ee; color:#9b2c24; border:1px solid #e7b7b2; font-size:13px;">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="search-box">
            <span>⌕</span>
            <input type="text" placeholder="Cari staff" aria-label="Cari staff" data-search-target="staff-members-list">
        </div>

        <div class="menu-list" id="staff-members-list" style="margin-top:16px;">
            @forelse ($staffMembers as $staffMember)
                <div class="status-row" data-search-item style="gap:12px; padding:12px 0;">
                    <div class="status-meta" style="min-width:0;">
                        <div class="status-name">{{ $staffMember->name }}</div>
                        <div class="status-email">{{ $staffMember->email }}</div>
                        <div class="menu-meta">{{ $staffMember->position }} · {{ $staffMember->active ? 'Aktif' : 'Nonaktif' }}</div>
                    </div>

                    <div style="display:flex; align-items:center; gap:10px; flex-shrink:0;">
                        <form method="POST" action="{{ route('owner.staff.toggle', $staffMember->id) }}">
                            @csrf
                            <button type="submit" class="toggle {{ $staffMember->active ? 'on' : '' }}" aria-label="{{ $staffMember->active ? 'Nonaktifkan' : 'Aktifkan' }} {{ $staffMember->name }}" style="border:none; cursor:pointer;"></button>
                        </form>

                        <div class="menu-more-wrap">
                            <button type="button" class="more-btn staff-more-trigger" data-staff-id="{{ $staffMember->id }}" aria-label="Aksi {{ $staffMember->name }}">⋮</button>
                            <div id="staff-action-{{ $staffMember->id }}" class="menu-action-menu" style="display:none;">
                                <button type="button" class="menu-action-item" data-edit-staff-id="{{ $staffMember->id }}">Edit staff</button>
                                <form method="POST" action="{{ route('owner.staff.delete', $staffMember->id) }}" onsubmit="return confirm('Hapus staff ini?')">
                                    @csrf
                                    <button type="submit" class="menu-action-item danger">Hapus staff</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="edit-staff-modal-{{ $staffMember->id }}" class="modal-overlay">
                    <div class="sheet small">
                        <h2 class="sheet-title">Edit Staff</h2>
                        <form method="POST" action="{{ route('owner.staff.update', $staffMember->id) }}" class="sheet-form">
                            @csrf
                            <input name="name" type="text" value="{{ $staffMember->name }}" placeholder="Nama staff" required>
                            <input name="email" type="email" value="{{ $staffMember->email }}" placeholder="Email" required>
                            <select name="position" aria-label="Role staff" required>
                                <option value="Barista" @selected($staffMember->position === 'Barista')>Barista</option>
                                <option value="Waiter" @selected($staffMember->position === 'Waiter')>Waiters</option>
                            </select>
                            <input name="password" type="password" placeholder="Password baru (opsional)" autocomplete="new-password">
                            <div class="sheet-actions">
                                <button type="button" class="action-link" data-close-modal="edit-staff-modal-{{ $staffMember->id }}">Batal</button>
                                <button type="submit" class="action-primary">Simpan</button>
                            </div>
                        </form>
                    </div>
                </div>
            @empty
                <div class="summary-box">Belum ada staff. Tekan + untuk menambahkan.</div>
            @endforelse
        </div>

        <button type="button" class="fab" data-modal-target="staff-modal" aria-label="Tambah staff">+</button>

        <div id="staff-modal" class="modal-overlay">
            <div class="sheet small">
                <h2 class="sheet-title">Tambah Staff</h2>
                <form method="POST" action="{{ route('owner.staff.store') }}" class="sheet-form">
                    @csrf
                    <input name="name" type="text" placeholder="Nama staff" required>
                    <input name="email" type="email" placeholder="Email" required>
                    <select name="position" aria-label="Role staff" required>
                        <option value="Barista">Barista</option>
                        <option value="Waiter">Waiters</option>
                    </select>
                    <input name="password" type="password" placeholder="Password akun staff (min. 8 karakter)" autocomplete="new-password" required>
                    <div class="sheet-actions">
                        <button type="button" class="action-link" data-close-modal="staff-modal">Batal</button>
                        <button type="submit" class="action-primary">Tambah</button>
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

            document.querySelectorAll('.staff-more-trigger').forEach(function (button) {
                button.addEventListener('click', function (event) {
                    event.stopPropagation();
                    const menu = document.getElementById('staff-action-' + button.dataset.staffId);
                    document.querySelectorAll('.menu-action-menu').forEach(function (actionMenu) {
                        if (actionMenu !== menu) {
                            actionMenu.style.display = 'none';
                        }
                    });
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                });
            });

            document.querySelectorAll('[data-edit-staff-id]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const id = button.dataset.editStaffId;
                    document.getElementById('staff-action-' + id).style.display = 'none';
                    document.getElementById('edit-staff-modal-' + id).classList.add('show');
                });
            });

            document.addEventListener('click', function () {
                document.querySelectorAll('.menu-action-menu').forEach(function (menu) {
                    menu.style.display = 'none';
                });
            });
        });
    </script>
@endsection
