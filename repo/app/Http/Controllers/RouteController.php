<?php

namespace App\Http\Controllers;

use App\Models\Route;
use App\Models\RouteVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RouteController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Route::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'waypoints' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $route = Route::create($request->only([
            'name', 'description', 'waypoints', 'metadata',
        ]));

        // Create initial version
        RouteVersion::create([
            'route_id' => $route->id,
            'version_number' => 1,
            'waypoints' => $route->waypoints ?? [],
            'prior_values' => null,
            'created_by' => $request->user()?->id,
            'change_reason' => 'Initial creation',
        ]);

        return response()->json([
            'message' => 'Route created successfully.',
            'data' => $route->load('versions'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $route = Route::with('versions')->findOrFail($id);

        return response()->json(['data' => $route]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'waypoints' => 'nullable|array',
            'metadata' => 'nullable|array',
            'status' => 'sometimes|required|string|max:50',
            'change_reason' => 'nullable|string|max:2000',
        ]);

        $route = Route::findOrFail($id);

        // Capture prior values before update
        $priorValues = $route->only(['name', 'description', 'status', 'waypoints', 'metadata']);

        $route->update($request->only([
            'name', 'description', 'waypoints', 'metadata', 'status',
        ]));

        // Create a new version
        $latestVersion = $route->versions()->max('version_number') ?? 0;

        RouteVersion::create([
            'route_id' => $route->id,
            'version_number' => $latestVersion + 1,
            'waypoints' => $route->waypoints ?? [],
            'prior_values' => $priorValues,
            'created_by' => $request->user()?->id,
            'change_reason' => $request->input('change_reason'),
        ]);

        return response()->json([
            'message' => 'Route updated successfully.',
            'data' => $route->fresh()->load('versions'),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $route = Route::findOrFail($id);
        $route->delete();

        return response()->json(['message' => 'Route deleted successfully.']);
    }

    public function versions(int $id): JsonResponse
    {
        $route = Route::findOrFail($id);

        return response()->json([
            'data' => $route->versions()->orderBy('version_number', 'desc')->get(),
        ]);
    }
}
