<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Resources\RoutePackageResource;
use App\Models\RoutePackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RoutePackageController extends Controller
{
    use AuthorizesRecordAccess;

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = RoutePackage::with('publisher');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return RoutePackageResource::collection($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'route_ids' => 'required|array|min:1',
            'route_ids.*' => 'integer|exists:routes,id',
            'target_group' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $package = RoutePackage::create([
            'name' => $request->name,
            'description' => $request->description,
            'status' => RoutePackage::STATUS_DRAFT,
            'route_ids' => $request->route_ids,
            'target_group' => $request->target_group,
            'metadata' => $request->metadata,
        ]);

        $package->load('publisher');

        return response()->json([
            'message' => 'Route package created.',
            'data' => new RoutePackageResource($package),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $package = RoutePackage::with('publisher')->findOrFail($id);
        $this->authorizeRecord($request, $package);

        return response()->json(['data' => new RoutePackageResource($package)]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'route_ids' => 'sometimes|required|array|min:1',
            'route_ids.*' => 'integer|exists:routes,id',
            'target_group' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ]);

        $package = RoutePackage::findOrFail($id);
        $this->authorizeMutation($request, $package);

        if ($package->status !== RoutePackage::STATUS_DRAFT) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => 'Only draft packages can be updated.',
            ], 422);
        }

        $package->update($request->only([
            'name', 'description', 'route_ids', 'target_group', 'metadata',
        ]));

        $package->load('publisher');

        return response()->json([
            'message' => 'Route package updated.',
            'data' => new RoutePackageResource($package),
        ]);
    }

    public function publish(Request $request, int $id): JsonResponse
    {
        $package = RoutePackage::findOrFail($id);
        $this->authorizeMutation($request, $package);

        if ($package->status !== RoutePackage::STATUS_DRAFT) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => 'Only draft packages can be published.',
            ], 422);
        }

        $package->update([
            'status' => RoutePackage::STATUS_PUBLISHED,
            'published_by' => $request->user()->id,
            'published_at' => now(),
        ]);

        $package->load('publisher');

        return response()->json([
            'message' => 'Route package published.',
            'data' => new RoutePackageResource($package),
        ]);
    }

    public function archive(Request $request, int $id): JsonResponse
    {
        $package = RoutePackage::findOrFail($id);
        $this->authorizeMutation($request, $package);

        if ($package->status !== RoutePackage::STATUS_PUBLISHED) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => 'Only published packages can be archived.',
            ], 422);
        }

        $package->update([
            'status' => RoutePackage::STATUS_ARCHIVED,
        ]);

        $package->load('publisher');

        return response()->json([
            'message' => 'Route package archived.',
            'data' => new RoutePackageResource($package),
        ]);
    }
}
