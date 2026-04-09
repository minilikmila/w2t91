<?php

namespace Tests\Unit;

use App\Services\LocationService;
use PHPUnit\Framework\TestCase;

class LocationServiceTest extends TestCase
{
    private LocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LocationService();
    }

    public function test_haversine_distance_same_point(): void
    {
        $distance = $this->service->haversineDistance(40.7128, -74.0060, 40.7128, -74.0060);
        $this->assertEquals(0.0, $distance);
    }

    public function test_haversine_distance_known_cities(): void
    {
        // New York to Los Angeles: approximately 3940 km
        $distance = $this->service->haversineDistance(40.7128, -74.0060, 34.0522, -118.2437);
        $this->assertGreaterThan(3900, $distance);
        $this->assertLessThan(4000, $distance);
    }

    public function test_haversine_distance_short_range(): void
    {
        // Two points ~1 km apart
        $distance = $this->service->haversineDistance(40.7128, -74.0060, 40.7218, -74.0060);
        $this->assertGreaterThan(0.9, $distance);
        $this->assertLessThan(1.1, $distance);
    }

    public function test_obfuscate_coordinates(): void
    {
        $result = $this->service->obfuscateCoordinates(40.7128456, -74.0060123);

        $this->assertEquals(40.71, $result['latitude']);
        $this->assertEquals(-74.01, $result['longitude']);
    }

    public function test_obfuscate_coordinates_already_rounded(): void
    {
        $result = $this->service->obfuscateCoordinates(40.71, -74.01);

        $this->assertEquals(40.71, $result['latitude']);
        $this->assertEquals(-74.01, $result['longitude']);
    }

    public function test_obfuscate_loses_precision(): void
    {
        $preciseLat = 40.7128456;
        $preciseLon = -74.0060123;

        $result = $this->service->obfuscateCoordinates($preciseLat, $preciseLon);

        // Obfuscated should differ from precise
        $this->assertNotEquals($preciseLat, $result['latitude']);
        $this->assertNotEquals($preciseLon, $result['longitude']);
    }
}
