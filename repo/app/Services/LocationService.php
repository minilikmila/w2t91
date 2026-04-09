<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Collection;

class LocationService
{
    private const EARTH_RADIUS_KM = 6371;
    private const DEFAULT_GEOFENCE_KM = 10;
    private const OBFUSCATION_DECIMALS = 2;

    /**
     * Calculate Haversine distance between two coordinate pairs in kilometers.
     */
    public function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);

        $a = sin($deltaLat / 2) * sin($deltaLat / 2)
            + cos($lat1Rad) * cos($lat2Rad)
            * sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Find locations within a geofence radius, sorted by distance.
     */
    public function findNearby(float $latitude, float $longitude, ?float $radiusKm = null): Collection
    {
        $radiusKm = $radiusKm ?? self::DEFAULT_GEOFENCE_KM;

        $locations = Location::where('is_active', true)->get();

        return $locations->map(function (Location $location) use ($latitude, $longitude) {
            $distance = $this->haversineDistance(
                $latitude,
                $longitude,
                $location->latitude,
                $location->longitude
            );
            $location->setAttribute('distance_km', round($distance, 3));
            return $location;
        })
        ->filter(fn (Location $location) => $location->distance_km <= $radiusKm)
        ->sortBy('distance_km')
        ->values();
    }

    /**
     * Obfuscate coordinates by rounding to fewer decimal places.
     */
    public function obfuscateCoordinates(float $latitude, float $longitude): array
    {
        return [
            'latitude' => round($latitude, self::OBFUSCATION_DECIMALS),
            'longitude' => round($longitude, self::OBFUSCATION_DECIMALS),
        ];
    }

    /**
     * Format a location for an authorized user (precise coordinates).
     */
    public function formatForAuthorized(Location $location, ?float $distanceKm = null): array
    {
        $data = [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type,
            'description' => $location->description,
            'address' => $location->address,
            'display_address' => $location->display_address,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'is_active' => $location->is_active,
            'metadata' => $location->metadata,
            'created_at' => $location->created_at?->toIso8601String(),
            'updated_at' => $location->updated_at?->toIso8601String(),
        ];

        if ($distanceKm !== null) {
            $data['distance_km'] = $distanceKm;
        }

        return $data;
    }

    /**
     * Format a location for an unauthorized user (obfuscated coordinates).
     */
    public function formatForUnauthorized(Location $location, ?float $distanceKm = null): array
    {
        $obfuscated = $this->obfuscateCoordinates($location->latitude, $location->longitude);

        $data = [
            'id' => $location->id,
            'name' => $location->name,
            'type' => $location->type,
            'display_address' => $location->display_address ?? 'Address available on request',
            'latitude' => $obfuscated['latitude'],
            'longitude' => $obfuscated['longitude'],
            'coordinates_precise' => false,
        ];

        if ($distanceKm !== null) {
            // Return only coarse distance bucket to prevent triangulation
            $data['distance_range'] = $this->coarseDistanceBucket($distanceKm);
        }

        return $data;
    }

    /**
     * Convert exact distance to a coarse bucket label to prevent triangulation.
     */
    private function coarseDistanceBucket(float $distanceKm): string
    {
        if ($distanceKm <= 1) {
            return 'within_1km';
        } elseif ($distanceKm <= 5) {
            return 'within_5km';
        } elseif ($distanceKm <= 10) {
            return 'within_10km';
        } else {
            return 'over_10km';
        }
    }

    /**
     * Check if a point is within the geofence of a location.
     */
    public function isWithinGeofence(float $lat, float $lon, Location $location, ?float $radiusKm = null): bool
    {
        $radiusKm = $radiusKm ?? self::DEFAULT_GEOFENCE_KM;
        $distance = $this->haversineDistance($lat, $lon, $location->latitude, $location->longitude);
        return $distance <= $radiusKm;
    }
}
