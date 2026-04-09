<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationApiTest extends TestCase
{
    use RefreshDatabase;

    private string $adminToken;
    private string $fieldAgentToken;
    private User $adminUser;
    private User $fieldAgentUser;
    private Learner $learner;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $fieldRole = Role::create(['name' => 'Field Agent', 'slug' => 'field_agent']);

        $allPerms = [
            'learners.view', 'learners.create', 'learners.update',
            'bookings.view', 'bookings.create', 'bookings.cancel',
            'resources.view', 'resources.manage',
        ];
        $fieldPerms = ['learners.view', 'learners.create', 'bookings.view', 'bookings.create'];

        foreach ($allPerms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $adminRole->permissions()->attach($perm);
            if (in_array($p, $fieldPerms)) {
                $fieldRole->permissions()->attach($perm);
            }
        }

        $this->adminUser = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $this->fieldAgentUser = User::create([
            'username' => 'agent', 'name' => 'Field Agent', 'email' => 'agent@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $fieldRole->id, 'is_active' => true,
        ]);

        $this->adminToken = 'authz-admin-token-padded-to-sixty-four-characters-long-here-ok!';
        ApiToken::create([
            'user_id' => $this->adminUser->id,
            'token' => hash('sha256', $this->adminToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->fieldAgentToken = 'authz-field-token-padded-to-sixty-four-characters-long-here-ok!';
        ApiToken::create([
            'user_id' => $this->fieldAgentUser->id,
            'token' => hash('sha256', $this->fieldAgentToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create([
            'first_name' => 'Jane', 'last_name' => 'Doe',
        ]);

        $resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 10]);

        $startTime = now()->addDays(3)->startOfHour();
        $this->booking = Booking::create([
            'resource_id' => $resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->adminUser->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(1),
        ]);
    }

    public function test_admin_can_access_any_learner(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->getJson("/api/learners/{$this->learner->id}");

        $response->assertStatus(200);
    }

    public function test_field_agent_cannot_view_unlinked_learner(): void
    {
        // Field agent has no operational link (booking/enrollment) to this learner
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->getJson("/api/learners/{$this->learner->id}");

        $response->assertStatus(403);
    }

    public function test_field_agent_can_view_learner_with_operational_link(): void
    {
        // Create a booking by the field agent to establish an operational link
        $resource = \App\Models\Resource::create(['name' => 'Room B', 'type' => 'room', 'capacity' => 5]);
        $startTime = now()->addDays(5)->startOfHour();
        \App\Models\Booking::create([
            'resource_id' => $resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->fieldAgentUser->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addHours(1),
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->getJson("/api/learners/{$this->learner->id}");

        $response->assertStatus(200);
    }

    public function test_field_agent_cannot_delete_learner(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->deleteJson("/api/learners/{$this->learner->id}");

        $response->assertStatus(403);
    }

    public function test_admin_can_cancel_any_booking(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->adminToken}"])
            ->postJson("/api/bookings/{$this->booking->id}/cancel");

        $response->assertStatus(200);
    }

    public function test_field_agent_cannot_cancel_others_booking(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->postJson("/api/bookings/{$this->booking->id}/cancel");

        $response->assertStatus(403);
    }

    public function test_field_agent_cannot_manage_resources(): void
    {
        $response = $this->withHeaders(['Authorization' => "Bearer {$this->fieldAgentToken}"])
            ->postJson('/api/resources', [
                'name' => 'Unauthorized Room',
                'type' => 'room',
                'capacity' => 5,
            ]);

        $response->assertStatus(403);
    }
}
