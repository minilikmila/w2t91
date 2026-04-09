<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Location;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LocationDisclosureTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $agentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $agentRole = Role::create(['name' => 'Field Agent', 'slug' => 'field_agent']);

        foreach (['locations.view', 'locations.manage', 'locations.view_precise'] as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $adminRole->permissions()->attach($perm);
            if ($p === 'locations.view') {
                $agentRole->permissions()->attach($perm);
            }
        }

        $admin = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $agent = User::create([
            'username' => 'agent', 'name' => 'Agent', 'email' => 'agent@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $agentRole->id, 'is_active' => true,
        ]);

        $this->adminToken = 'disc-admin-tkn-padded-to-exactly-sixty-four-characters-exactly!';
        ApiToken::create([
            'user_id' => $admin->id,
            'token' => hash('sha256', $this->adminToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->agentToken = 'disc-agent-tkn-padded-to-exactly-sixty-four-characters-exactly!';
        ApiToken::create([
            'user_id' => $agent->id,
            'token' => hash('sha256', $this->agentToken),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_agent_nearby_returns_distance_range_not_exact(): void
    {
        Location::create([
            'name' => 'Shelter', 'type' => 'shelter',
            'latitude' => 40.7130, 'longitude' => -74.0060, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->agentToken}"])
            ->getJson('/api/locations/nearby?latitude=40.7128&longitude=-74.0060&radius_km=10');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('distance_range', $data[0]);
        $this->assertArrayNotHasKey('distance_km', $data[0]);
    }

    public function test_admin_nearby_returns_exact_distance(): void
    {
        Location::create([
            'name' => 'Shelter', 'type' => 'shelter',
            'latitude' => 40.7130, 'longitude' => -74.0060, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/locations/nearby?latitude=40.7128&longitude=-74.0060&radius_km=10');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('distance_km', $data[0]);
    }

    public function test_agent_geofence_check_has_no_exact_distance(): void
    {
        $location = Location::create([
            'name' => 'Base', 'type' => 'office',
            'latitude' => 40.7128, 'longitude' => -74.0060, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->agentToken}"])
            ->getJson("/api/locations/{$location->id}/geofence?latitude=40.7130&longitude=-74.0062");

        $response->assertStatus(200);
        $response->assertJsonStructure(['location_id', 'within_geofence']);
        $this->assertArrayNotHasKey('distance_km', $response->json());
    }

    public function test_admin_geofence_check_includes_exact_distance(): void
    {
        $location = Location::create([
            'name' => 'Base', 'type' => 'office',
            'latitude' => 40.7128, 'longitude' => -74.0060, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/locations/{$location->id}/geofence?latitude=40.7130&longitude=-74.0062");

        $response->assertStatus(200);
        $response->assertJsonStructure(['location_id', 'within_geofence', 'distance_km']);
    }

    public function test_agent_sees_obfuscated_not_precise_coordinates(): void
    {
        $location = Location::create([
            'name' => 'Safe House', 'type' => 'shelter',
            'latitude' => 40.7128456, 'longitude' => -74.0060123,
            'display_address' => 'Downtown', 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->agentToken}"])
            ->getJson("/api/locations/{$location->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.coordinates_precise', false);
        $response->assertJsonPath('data.latitude', 40.71);
        $response->assertJsonPath('data.longitude', -74.01);
    }
}
