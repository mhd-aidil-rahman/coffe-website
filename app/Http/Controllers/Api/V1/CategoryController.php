<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\MenuCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(private MenuCatalog $catalog) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->catalog->categories()]);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->catalog->saveCategory($request->only(['name', 'description']))], 201);
    }

    public function update(Request $request, int $categoryId): JsonResponse
    {
        return response()->json(['data' => $this->catalog->saveCategory($request->only(['name', 'description']), $categoryId)]);
    }

    public function destroy(int $categoryId): JsonResponse
    {
        $this->catalog->deleteCategory($categoryId);

        return response()->json(['message' => 'Kategori berhasil dihapus.']);
    }
}
