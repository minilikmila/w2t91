<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Route as RouteModel;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResourceScheduleRouteApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $perms = ['resources.view', 'resources.manage'];
        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'rsrc-token-padded-to-exactly-sixty-four-characters-long-here-ok';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_create_resource(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/resources', [
                'name' => 'Conference Room A',
                'type' => 'room',
                'capacity' => 20,
            ]);

        $response->assertStatus(201);
    }

    public function test_list_resources(): void
    {
        Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 10]);
        Resource::create(['name' => 'Room B', 'type' => 'room', 'capacity' => 15]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/resources');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_create_schedule(): void
    {
        $resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 10]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/schedules', [
                'resource_id' => $resource->id,
                'date' => now()->addDays(1)->format('Y-m-d'),
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);

        $response->assertStatus(201);
    }

    public function test_get_schedule_slots(): void
    {
        $resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 10]);
        $schedule = Schedule::create([
            'resource_id' => $resource->id,
            'date' => now()->addDays(1)->format('Y-m-d'),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'slot_duration_minutes' => 15,
            'capacity_per_slot' => 1,
            'is_active' => true,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/schedules/{$schedule->id}/slots");

        $response->assertStatus(200)
            ->assertJsonCount(4, 'data');
    }

    public function test_create_route_with_version(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/routes', [
                'name' => 'Route Alpha',
                'waypoints' => [
                    ['lat' => 1.0, 'lng' => 2.0],
                    ['lat' => 3.0, 'lng' => 4.0],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['versions']]);
    }

    public function test_update_route_creates_version(): void
    {
        $createResponse = $this->withHeaders($this->auth())
            ->postJson('/api/routes', [
                'name' => 'Route Beta',
                'waypoints' => [
                    ['lat' => 1.0, 'lng' => 2.0],
                ],
            ]);

        $routeId = $createResponse->json('data.id');

        $this->withHeaders($this->auth())
            ->putJson("/api/routes/{$routeId}", [
                'waypoints' => [
                    ['lat' => 5.0, 'lng' => 6.0],
                    ['lat' => 7.0, 'lng' => 8.0],
                ],
                'change_reason' => 'Updated waypoints',
            ])->assertStatus(200);

        $versionsResponse = $this->withHeaders($this->auth())
            ->getJson("/api/routes/{$routeId}/versions");

        $versionsResponse->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_delete_resource(): void
    {
        $resource = Resource::create(['name' => 'Room Z', 'type' => 'room', 'capacity' => 5]);

        $response = $this->withHeaders($this->auth())
            ->deleteJson("/api/resources/{$resource->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('resources', ['id' => $resource->id]);
    }
}
