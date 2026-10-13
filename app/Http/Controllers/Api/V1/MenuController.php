<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MenuItemResource;
use App\Models\StaffMember;
use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MenuController extends Controller
{
    public function __construct(private MenuCatalog $catalog) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['active' => ['sometimes', 'boolean'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100']]);
        $query = $this->catalog->query()->orderBy('menu_items.name')->orderBy('menu_items.id');
        if ($request->user() instanceof StaffMember && $request->user()->position === 'Waiter') {
            $query->where('menu_items.active', true);
        } elseif ($request->has('active')) {
            $query->where('menu_items.active', $request->boolean('active'));
        }

        return MenuItemResource::collection($query->paginate($request->integer('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $item = $this->catalog->saveItem($request->only(['name', 'price', 'category_id', 'description', 'active', 'image_path']));

        return (new MenuItemResource($item))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $menuItemId): MenuItemResource
    {
        return new MenuItemResource($this->catalog->saveItem($request->only(['name', 'price', 'category_id', 'description', 'image_path']), $menuItemId));
    }

    public function availability(Request $request, int $menuItemId): MenuItemResource
    {
        $request->validate(['active' => ['required', 'boolean']]);

        return new MenuItemResource($this->catalog->setAvailable($menuItemId, $request->boolean('active')));
    }

    public function destroy(int $menuItemId): JsonResponse
    {
        $this->catalog->deleteItem($menuItemId);

        return response()->json(['message' => 'Menu berhasil dihapus.']);
    }
}
