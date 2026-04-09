<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Models\Resource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    use AuthorizesRecordAccess;

    public function index(Request $request): JsonResponse
    {
        $query = Resource::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $resource = Resource::create($request->only([
            'name', 'type', 'description', 'capacity', 'is_active', 'metadata',
        ]));

        return response()->json([
            'message' => 'Resource created successfully.',
            'data' => $resource,
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $this->authorizeRecord($request, $resource);

        return response()->json(['data' => $resource]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'capacity' => 'nullable|integer|min:1',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $resource = Resource::findOrFail($id);
        $this->authorizeMutation($request, $resource);
        $resource->update($request->only([
            'name', 'type', 'description', 'capacity', 'is_active', 'metadata',
        ]));

        return response()->json([
            'message' => 'Resource updated successfully.',
            'data' => $resource->fresh(),
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $resource = Resource::findOrFail($id);
        $this->authorizeMutation($request, $resource);
        $resource->delete();

        return response()->json(['message' => 'Resource deleted successfully.']);
    }
}
