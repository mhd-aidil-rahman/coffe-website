@extends('layouts.mobile-shell')

@section('content')
    <div class="page">
        <div class="dashboard-header" style="padding-top:0;">
            <a href="{{ route($menuBackRoute) }}" style="font-size:34px; color:#1d1d1d; text-decoration:none;">←</a>
            <h1 style="font-size:26px; margin:0;">Menu Management</h1>
            <div style="width:24px;"></div>
        </div>

        <div class="live-sync-status" data-live-sync-status role="status" aria-live="polite"></div>

        @if (session('success'))
            <div style="margin: 10px 0 14px; padding: 10px 12px; border-radius: 10px; background: rgba(13, 91, 77, 0.08); color: #0d5b4d; border: 1px solid rgba(13, 91, 77, 0.16); font-size: 13px; font-weight: 600;">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div role="alert" style="margin: 10px 0 14px; padding: 10px 12px; border-radius: 10px; background: #fff0ee; color: #9b2c24; border: 1px solid #e7b7b2; font-size: 13px;">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <div class="tabbar">
            <a href="{{ route($menuManagementRoute, ['tab' => 'menu']) }}" class="tab {{ $tab === 'menu' ? 'active' : '' }}" style="text-decoration:none;">Menu Items</a>
            <a href="{{ route($menuManagementRoute, ['tab' => 'categories']) }}" class="tab {{ $tab === 'categories' ? 'active' : '' }}" style="text-decoration:none;">Categories</a>
        </div>

        @if ($tab === 'menu')
            <div class="search-box">
                <span>⌕</span>
                <input type="text" placeholder="Cari menu" aria-label="Cari menu" data-search-target="menu-items-list">
            </div>

            <div class="menu-list" id="menu-items-list" data-live-sync="owner-menu-management" style="margin-top:16px;">
                @forelse ($items as $item)
                    <div class="menu-item" data-search-item style="grid-template-columns: 52px 1fr auto;">
                        <div class="thumb">
                            @if ($item->image_path)
                                <img src="{{ asset($item->image_path) }}" alt="{{ $item->name }}">
                            @else
                                ☕
                            @endif
                        </div>
                        <div>
                            <div class="menu-name" style="font-size:18px; margin-bottom:2px;">{{ $item->name }}</div>
                            <div class="menu-meta">Rp {{ number_format($item->price, 0, ',', '.') }} · {{ $item->category_name }} · {{ $item->active ? 'Aktif' : 'Nonaktif' }}</div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px; position:relative;">
                            <form method="POST" action="{{ route('owner.menu.toggle', $item->id) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="toggle {{ $item->active ? 'on' : '' }}" aria-label="toggle menu {{ $item->name }}" style="border:none; padding:0; cursor:pointer;"></button>
                            </form>

                            <div class="menu-more-wrap">
                                <button type="button" class="more-btn menu-more-trigger" data-menu-id="{{ $item->id }}">⋮</button>
                                <div id="menu-action-{{ $item->id }}" class="menu-action-menu" style="display:none;">
                                    <button type="button" class="menu-action-item" data-edit-id="{{ $item->id }}">Edit menu</button>
                                    <form method="POST" action="{{ route('owner.menu.delete', $item->id) }}" onsubmit="return confirm('Hapus menu ini?')">
                                        @csrf
                                        <button type="submit" class="menu-action-item danger">Hapus menu</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="edit-menu-modal-{{ $item->id }}" class="modal-overlay">
                        <div class="sheet">
                            <h2 class="sheet-title">Edit Menu Item</h2>

                            <form method="POST" action="{{ route('owner.menu.update', $item->id) }}" enctype="multipart/form-data" class="sheet-form">
                                @csrf
                                <input name="name" type="text" value="{{ $item->name }}" required>
                                <input name="description" type="text" value="{{ $item->description }}" placeholder="Deskripsi">
                                <input name="price" type="number" min="1" value="{{ $item->price }}" required>

                                <select name="category_id" required>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $category->id == $item->category_id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                </select>

                                <label class="upload-box">
                                    <span class="upload-label-text">Gambar Menu</span>
                                    <span class="upload-icon">▣</span>
                                    <input type="file" name="image" accept="image/*" class="file-input">
                                </label>
                                <img src="" alt="Preview gambar" class="upload-preview hidden" style="width:100%; height:220px; object-fit:cover; border-radius:12px; border:1px solid #d6d8d4;">

                                @if ($item->image_path)
                                    <img src="{{ asset($item->image_path) }}" alt="{{ $item->name }}" class="current-image-preview" style="width:100%; height:220px; object-fit:cover; border-radius:12px; border:1px solid #d6d8d4;">
                                @endif

                                <div class="sheet-actions">
                                    <button type="button" class="action-link" data-close-modal="edit-menu-modal-{{ $item->id }}">Batal</button>
                                    <button type="submit" class="action-primary">Update</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,0.16); color: #4d4d4d; text-align:center;">
                        Belum ada menu.
                    </div>
                @endforelse
            </div>

            <button type="button" class="fab" data-modal-target="menu-modal" aria-label="Tambah menu">+</button>

            <div id="menu-modal" class="modal-overlay">
                <div class="sheet">
                    <h2 class="sheet-title">Tambah Menu Item</h2>

                    <form method="POST" action="{{ route('owner.menu.store') }}" enctype="multipart/form-data" class="sheet-form">
                        @csrf
                        <input name="name" type="text" placeholder="Nama Menu" required>
                        <input name="description" type="text" placeholder="Deskripsi">
                        <input name="price" type="number" min="1" placeholder="Harga" required>

                        <select name="category_id" required>
                            <option value="">Pilih Kategori</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>

                        <label class="upload-box">
                            <span class="upload-label-text">Gambar Menu</span>
                            <span class="upload-icon">▣</span>
                            <input type="file" name="image" accept="image/*" class="file-input">
                        </label>
                        <img src="" alt="Preview gambar" class="upload-preview hidden" style="width:100%; height:220px; object-fit:cover; border-radius:12px; border:1px solid #d6d8d4;">

                        <div class="sheet-actions">
                            <button type="button" class="action-link" data-close-modal="menu-modal">Batal</button>
                            <button type="submit" class="action-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        @else
            <div class="search-box" style="margin-top: 4px;">
                <span>⌕</span>
                <input type="text" placeholder="Cari kategori" aria-label="Cari kategori" data-search-target="menu-categories-list">
            </div>

            <div class="menu-list" id="menu-categories-list" data-live-sync="owner-menu-categories" style="margin-top:16px;">
                @forelse ($categories as $category)
                    <div class="category-row" data-search-item>
                        <div>
                            <div class="category-name">{{ $category->name }}</div>
                            @if ($category->description)
                                <span class="category-desc">{{ $category->description }}</span>
                            @endif
                        </div>
                        <div class="menu-more-wrap">
                            <button type="button" class="more-btn menu-more-trigger" data-menu-id="category-{{ $category->id }}" aria-label="Aksi kategori {{ $category->name }}">⋮</button>
                            <div id="menu-action-category-{{ $category->id }}" class="menu-action-menu" style="display:none;">
                                <button type="button" class="menu-action-item" data-edit-category-id="{{ $category->id }}">Edit kategori</button>
                                <form method="POST" action="{{ route('owner.categories.delete', $category->id) }}" onsubmit="return confirm('Hapus kategori ini?')">
                                    @csrf
                                    <button type="submit" class="menu-action-item danger">Hapus kategori</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="edit-category-modal-{{ $category->id }}" class="modal-overlay">
                        <div class="sheet small">
                            <h2 class="sheet-title">Edit Kategori</h2>
                            <form method="POST" action="{{ route('owner.categories.update', $category->id) }}" class="sheet-form">
                                @csrf
                                <input name="name" type="text" value="{{ $category->name }}" required>
                                <input name="description" type="text" value="{{ $category->description }}" placeholder="Deskripsi">
                                <div class="sheet-actions">
                                    <button type="button" class="action-link" data-close-modal="edit-category-modal-{{ $category->id }}">Batal</button>
                                    <button type="submit" class="action-primary">Simpan</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @empty
                    <div style="padding: 16px; border-radius: 12px; border: 1px solid var(--line); background: rgba(255,255,255,0.16); color: #4d4d4d; text-align:center;">
                        Belum ada kategori.
                    </div>
                @endforelse
            </div>

            <button type="button" class="fab" data-modal-target="category-modal" aria-label="Tambah kategori">+</button>

            <div id="category-modal" class="modal-overlay">
                <div class="sheet small">
                    <h2 class="sheet-title">Tambah Kategori</h2>

                    <form method="POST" action="{{ route('owner.categories.store') }}" class="sheet-form">
                        @csrf
                        <input name="name" type="text" placeholder="Nama Kategori" required>
                        <input name="description" type="text" placeholder="Deskripsi">

                        <div class="sheet-actions">
                            <button type="button" class="action-link" data-close-modal="category-modal">Batal</button>
                            <button type="submit" class="action-primary">Tambah</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.addEventListener('click', function (event) {
                const clickTarget = event.target instanceof Element ? event.target : null;

                if (! clickTarget) {
                    return;
                }

                const openButton = clickTarget.closest('[data-modal-target]');
                if (openButton) {
                    document.getElementById(openButton.dataset.modalTarget)?.classList.add('show');

                    return;
                }

                const closeButton = clickTarget.closest('[data-close-modal]');
                if (closeButton) {
                    document.getElementById(closeButton.dataset.closeModal)?.classList.remove('show');

                    return;
                }

                const modal = clickTarget.classList.contains('modal-overlay') ? clickTarget : null;
                if (modal) {
                    modal.classList.remove('show');

                    return;
                }

                const moreButton = clickTarget.closest('.menu-more-trigger');
                if (moreButton) {
                    event.stopPropagation();
                    const menu = document.getElementById('menu-action-' + moreButton.dataset.menuId);

                    document.querySelectorAll('.menu-action-menu').forEach(function (item) {
                        if (item !== menu) {
                            item.style.display = 'none';
                        }
                    });

                    if (menu) {
                        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                    }

                    return;
                }

                const editButton = clickTarget.closest('[data-edit-id]');
                if (editButton) {
                    const modal = document.getElementById('edit-menu-modal-' + editButton.dataset.editId);
                    if (modal) {
                        modal.classList.add('show');
                        const menu = document.getElementById('menu-action-' + editButton.dataset.editId);
                        if (menu) {
                            menu.style.display = 'none';
                        }
                    }

                    return;
                }

                const editCategoryButton = clickTarget.closest('[data-edit-category-id]');
                if (editCategoryButton) {
                    const modal = document.getElementById('edit-category-modal-' + editCategoryButton.dataset.editCategoryId);
                    if (modal) {
                        modal.classList.add('show');
                        const menu = document.getElementById('menu-action-category-' + editCategoryButton.dataset.editCategoryId);
                        if (menu) {
                            menu.style.display = 'none';
                        }
                    }

                    return;
                }

                document.querySelectorAll('.menu-action-menu').forEach(function (menu) {
                    menu.style.display = 'none';
                });
            });

            document.addEventListener('change', function (event) {
                const input = event.target instanceof HTMLInputElement ? event.target : null;

                if (! input?.matches('.file-input')) {
                    return;
                }

                const file = input.files && input.files[0];
                const preview = input.closest('.sheet-form').querySelector('.upload-preview');

                if (! file || ! preview) {
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    preview.src = loadEvent.target.result;
                    preview.classList.remove('hidden');
                };
                reader.readAsDataURL(file);
            });
        });
    </script>
@endsection
