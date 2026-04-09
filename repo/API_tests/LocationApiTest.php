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

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $agentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $agentRole = Role::create(['name' => 'Field Agent', 'slug' => 'field_agent']);

        $perms = ['locations.view', 'locations.manage', 'locations.view_precise'];
        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $adminRole->permissions()->attach($perm);
            if ($p === 'locations.view') {
                $agentRole->permissions()->attach($perm);
            }
        }

        $admin = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $agent = User::create([
            'username' => 'agent', 'name' => 'Agent', 'email' => 'agent@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $agentRole->id, 'is_active' => true,
        ]);

        $this->adminToken = 'loc-admin-token-padded-to-sixty-four-characters-long-exactly-ok!';
        ApiToken::create([
            'user_id' => $admin->id,
            'token' => hash('sha256', $this->adminToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->agentToken = 'loc-agent-token-padded-to-sixty-four-characters-long-exactly-ok!';
        ApiToken::create([
            'user_id' => $agent->id,
            'token' => hash('sha256', $this->agentToken),
            'expires_at' => now()->addHours(24),
        ]);
    }

    public function test_admin_sees_precise_coordinates(): void
    {
        $location = Location::create([
            'name' => 'Safe House', 'type' => 'shelter',
            'latitude' => 40.7128456, 'longitude' => -74.0060123,
            'display_address' => 'Downtown Area',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/locations/{$location->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.latitude', 40.7128456)
            ->assertJsonPath('data.longitude', -74.0060123);
    }

    public function test_field_agent_sees_obfuscated_coordinates(): void
    {
        $location = Location::create([
            'name' => 'Safe House', 'type' => 'shelter',
            'latitude' => 40.7128456, 'longitude' => -74.0060123,
            'display_address' => 'Downtown Area',
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->agentToken}"])
            ->getJson("/api/locations/{$location->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.latitude', 40.71)
            ->assertJsonPath('data.longitude', -74.01)
            ->assertJsonPath('data.coordinates_precise', false);
    }

    public function test_create_location(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson('/api/locations', [
                'name' => 'New Shelter',
                'type' => 'shelter',
                'latitude' => 34.0522,
                'longitude' => -118.2437,
                'display_address' => 'West Side',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Shelter');
    }

    public function test_nearby_search(): void
    {
        Location::create([
            'name' => 'Close', 'type' => 'shelter',
            'latitude' => 40.7130, 'longitude' => -74.0060, 'is_active' => true,
        ]);
        Location::create([
            'name' => 'Far', 'type' => 'shelter',
            'latitude' => 34.0522, 'longitude' => -118.2437, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson('/api/locations/nearby?latitude=40.7128&longitude=-74.0060&radius_km=10');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_geofence_check(): void
    {
        $location = Location::create([
            'name' => 'Base', 'type' => 'office',
            'latitude' => 40.7128, 'longitude' => -74.0060, 'is_active' => true,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/locations/{$location->id}/geofence?latitude=40.7130&longitude=-74.0062");

        $response->assertStatus(200)
            ->assertJsonPath('within_geofence', true);
    }

    public function test_field_agent_cannot_create_location(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->agentToken}"])
            ->postJson('/api/locations', [
                'name' => 'Test', 'type' => 'shelter',
                'latitude' => 40.0, 'longitude' => -74.0,
            ]);

        $response->assertStatus(403);
    }
}
