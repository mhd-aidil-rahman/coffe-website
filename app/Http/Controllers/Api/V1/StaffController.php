<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\StaffResource;
use App\Models\StaffMember;
use App\Support\StaffManagement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StaffController extends Controller
{
    public function __construct(private StaffManagement $staff) {}

    public function index(): AnonymousResourceCollection
    {
        return StaffResource::collection($this->staff->query()->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => new StaffResource($this->staff->save($request->only(['name', 'email', 'position', 'password'])))], 201);
    }

    public function show(int $staffId): StaffResource
    {
        return new StaffResource(StaffMember::query()->findOrFail($staffId));
    }

    public function update(Request $request, int $staffId): StaffResource
    {
        return new StaffResource($this->staff->save($request->only(['name', 'email', 'position', 'password']), $staffId));
    }

    public function status(Request $request, int $staffId): JsonResponse
    {
        $request->validate(['active' => ['required', 'boolean']]);

        return response()->json(['data' => new StaffResource($this->staff->setActive($staffId, $request->boolean('active')))]);
    }

    public function destroy(int $staffId): JsonResponse
    {
        $this->staff->delete($staffId);

        return response()->json(['message' => 'Staff berhasil dihapus.']);
    }
}
