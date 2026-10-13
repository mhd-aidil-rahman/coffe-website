<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuCatalog
{
    public function query(): Builder
    {
        return DB::table('menu_items')->join('menu_categories', 'menu_items.category_id', '=', 'menu_categories.id')->select('menu_items.*', 'menu_categories.name as category_name');
    }

    /** @return Collection<int, object> */
    public function categories(): Collection
    {
        return DB::table('menu_categories')->orderBy('name')->orderBy('id')->get();
    }

    /** @param array{name?: string, description?: ?string} $data */
    public function saveCategory(array $data, ?int $id = null): object
    {
        if ($id !== null) {
            abort_unless(DB::table('menu_categories')->where('id', $id)->exists(), 404);
        }
        $validated = Validator::make($data, ['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string', 'max:255']])->validate();
        if ($id === null) {
            $id = DB::table('menu_categories')->insertGetId([...$validated, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('menu_categories')->where('id', $id)->update([...$validated, 'updated_at' => now()]);
        }

        return DB::table('menu_categories')->where('id', $id)->firstOrFail();
    }

    public function deleteCategory(int $id): void
    {
        abort_unless(DB::table('menu_categories')->where('id', $id)->exists(), 404);
        if (DB::table('menu_items')->where('category_id', $id)->exists()) {
            throw ValidationException::withMessages(['category' => 'Kategori masih digunakan oleh menu. Hapus atau pindahkan menu terlebih dahulu.'])->status(409);
        }
        DB::table('menu_categories')->where('id', $id)->delete();
    }

    /** @param array{name?: string, price?: int|string, category_id?: int|string, description?: ?string, active?: bool|int|string, image_path?: ?string} $data */
    public function saveItem(array $data, ?int $id = null, ?UploadedFile $image = null): object
    {
        $previous = $id === null ? null : $this->findItem($id);
        $rules = [
            'name' => ['required', 'string', 'max:255'], 'price' => ['required', 'integer', 'min:1'],
            'category_id' => ['required', 'integer', 'exists:menu_categories,id'],
            'description' => ['nullable', 'string', 'max:255'], 'image_path' => ['nullable', 'string', 'max:2048'],
        ];
        if ($id === null) {
            $rules['active'] = ['sometimes', 'boolean'];
        }
        $payload = Validator::make($data, $rules)->validate();
        if ($image !== null) {
            Validator::make(['image' => $image], ['image' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:8192']])->validate();
            $directory = public_path('uploads/menu');
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            $filename = Str::uuid().'-'.Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)).'.'.$image->extension();
            $image->move($directory, $filename);
            $payload['image_path'] = '/uploads/menu/'.$filename;
        }
        if ($id === null) {
            $id = DB::table('menu_items')->insertGetId([...$payload, 'active' => $payload['active'] ?? true, 'created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('menu_items')->where('id', $id)->update([...$payload, 'updated_at' => now()]);
        }
        if ($previous !== null && array_key_exists('image_path', $payload) && $previous->image_path !== $payload['image_path']) {
            $this->deleteImage($previous->image_path);
        }

        return $this->findItem($id);
    }

    public function findItem(int $id): object
    {
        return $this->query()->where('menu_items.id', $id)->firstOrFail();
    }

    public function setAvailable(int $id, bool $active): object
    {
        $this->findItem($id);
        DB::table('menu_items')->where('id', $id)->update(['active' => $active, 'updated_at' => now()]);

        return $this->findItem($id);
    }

    public function toggleAvailable(int $id): object
    {
        return $this->setAvailable($id, ! $this->findItem($id)->active);
    }

    public function deleteItem(int $id): void
    {
        $item = $this->findItem($id);
        DB::table('menu_items')->where('id', $id)->delete();
        $this->deleteImage($item->image_path);
    }

    private function deleteImage(?string $path): void
    {
        if ($path !== null && preg_match('#\A/uploads/menu/[^/\\\\]+\z#', $path) === 1 && is_file(public_path($path))) {
            unlink(public_path($path));
        }
    }
}
