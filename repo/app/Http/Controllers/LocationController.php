<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    private LocationService $locationService;

    public function __construct(LocationService $locationService)
    {
        $this->locationService = $locationService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Location::query();

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active === 'true');
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('display_address', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->get('per_page', 25), 100);
        $locations = $query->paginate($perPage);

        $canViewPrecise = $this->canViewPreciseCoordinates($request);

        $locations->getCollection()->transform(function (Location $location) use ($canViewPrecise) {
            return $canViewPrecise
                ? $this->locationService->formatForAuthorized($location)
                : $this->locationService->formatForUnauthorized($location);
        });

        return response()->json($locations);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:1000',
            'display_address' => 'nullable|string|max:255',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $location = Location::create($request->only([
            'name', 'type', 'description', 'address', 'display_address',
            'latitude', 'longitude', 'is_active', 'metadata',
        ]));

        return response()->json([
            'message' => 'Location created successfully.',
            'data' => $this->locationService->formatForAuthorized($location),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $canViewPrecise = $this->canViewPreciseCoordinates($request);
        $data = $canViewPrecise
            ? $this->locationService->formatForAuthorized($location)
            : $this->locationService->formatForUnauthorized($location);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'address' => 'nullable|string|max:1000',
            'display_address' => 'nullable|string|max:255',
            'latitude' => 'sometimes|required|numeric|between:-90,90',
            'longitude' => 'sometimes|required|numeric|between:-180,180',
            'is_active' => 'nullable|boolean',
            'metadata' => 'nullable|array',
        ]);

        $location = Location::findOrFail($id);
        $location->update($request->only([
            'name', 'type', 'description', 'address', 'display_address',
            'latitude', 'longitude', 'is_active', 'metadata',
        ]));

        return response()->json([
            'message' => 'Location updated successfully.',
            'data' => $this->locationService->formatForAuthorized($location->fresh()),
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $location->delete();

        return response()->json(['message' => 'Location deleted successfully.']);
    }

    /**
     * Find nearby locations within geofence radius, sorted by distance.
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',
        ]);

        $nearby = $this->locationService->findNearby(
            (float) $request->latitude,
            (float) $request->longitude,
            $request->has('radius_km') ? (float) $request->radius_km : null
        );

        $canViewPrecise = $this->canViewPreciseCoordinates($request);

        $formatted = $nearby->map(function (Location $location) use ($canViewPrecise) {
            $distanceKm = $location->getAttribute('distance_km');
            return $canViewPrecise
                ? $this->locationService->formatForAuthorized($location, $distanceKm)
                : $this->locationService->formatForUnauthorized($location, $distanceKm);
        })->values();

        return response()->json([
            'data' => $formatted,
            'count' => $formatted->count(),
        ]);
    }

    /**
     * Check if a point is within a location's geofence.
     */
    public function geofenceCheck(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius_km' => 'nullable|numeric|min:0.1|max:500',
        ]);

        $location = Location::findOrFail($id);

        $isWithin = $this->locationService->isWithinGeofence(
            (float) $request->latitude,
            (float) $request->longitude,
            $location,
            $request->has('radius_km') ? (float) $request->radius_km : null
        );

        $distance = $this->locationService->haversineDistance(
            (float) $request->latitude,
            (float) $request->longitude,
            $location->latitude,
            $location->longitude
        );

        return response()->json([
            'location_id' => $location->id,
            'within_geofence' => $isWithin,
            'distance_km' => round($distance, 3),
        ]);
    }

    /**
     * Determine if the current user can view precise coordinates.
     */
    private function canViewPreciseCoordinates(Request $request): bool
    {
        $user = $request->user();

        if (!$user) {
            return false;
        }

        return $user->hasPermission('locations.view_precise');
    }
}
