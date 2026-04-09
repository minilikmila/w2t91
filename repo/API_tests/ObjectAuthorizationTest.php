<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Approval;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ObjectAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private string $reviewerToken;
    private User $admin;
    private User $reviewer;
    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $reviewerRole = Role::create(['name' => 'Reviewer', 'slug' => 'reviewer']);

        $allPerms = [
            'enrollments.create', 'enrollments.view', 'enrollments.update',
            'enrollments.approve', 'enrollments.cancel',
            'bookings.create', 'bookings.view', 'bookings.update', 'bookings.cancel',
            'resources.view', 'resources.manage',
        ];
        $reviewerPerms = ['enrollments.view', 'enrollments.approve', 'bookings.view', 'resources.view'];

        foreach ($allPerms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $adminRole->permissions()->attach($perm);
            if (in_array($p, $reviewerPerms)) {
                $reviewerRole->permissions()->attach($perm);
            }
        }

        $this->admin = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $adminRole->id, 'is_active' => true,
        ]);

        $this->reviewer = User::create([
            'username' => 'reviewer', 'name' => 'Reviewer', 'email' => 'reviewer@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $reviewerRole->id, 'is_active' => true,
        ]);

        $this->reviewerToken = 'obj-auth-reviewer-token-padded-sixty-four-characters-exactly-ok!';
        ApiToken::create([
            'user_id' => $this->reviewer->id,
            'token' => hash('sha256', $this->reviewerToken),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create(['first_name' => 'John', 'last_name' => 'Doe']);
    }

    private function reviewerAuth(): array
    {
        return ['Authorization' => "Bearer {$this->reviewerToken}"];
    }

    // --- Enrollment Authorization ---

    public function test_reviewer_cannot_transition_enrollment_they_dont_own(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'last_actor_id' => $this->admin->id,
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->putJson("/api/enrollments/{$enrollment->id}/transition", [
                'status' => 'submitted',
            ]);

        $response->assertStatus(403);
    }

    public function test_reviewer_cannot_cancel_enrollment_they_dont_own(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'enrolled',
            'last_actor_id' => $this->admin->id,
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->postJson("/api/enrollments/{$enrollment->id}/cancel");

        $response->assertStatus(403);
    }

    // --- Approval Authorization ---

    public function test_reviewer_can_access_approval_assigned_to_them(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'under_review',
            'max_approval_levels' => 1,
            'current_approval_level' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $approval = Approval::create([
            'enrollment_id' => $enrollment->id,
            'reviewer_id' => $this->reviewer->id,
            'level' => 1,
            'status' => 'in_review',
        ]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->getJson("/api/approvals/{$approval->id}");

        $response->assertStatus(200);
    }

    public function test_reviewer_cannot_access_approval_assigned_to_others(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'under_review',
            'max_approval_levels' => 1,
            'current_approval_level' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $approval = Approval::create([
            'enrollment_id' => $enrollment->id,
            'reviewer_id' => $this->admin->id,
            'level' => 1,
            'status' => 'in_review',
        ]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->getJson("/api/approvals/{$approval->id}");

        $response->assertStatus(403);
    }

    // --- Booking/Waitlist Authorization ---

    public function test_reviewer_cannot_cancel_booking_they_dont_own(): void
    {
        $resource = Resource::create(['name' => 'Room', 'type' => 'room', 'capacity' => 1]);
        $startTime = now()->addDays(3)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->admin->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'version' => 1,
        ]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(403);
    }

    // --- Resource Authorization ---

    public function test_reviewer_cannot_update_resource(): void
    {
        $resource = Resource::create(['name' => 'Room', 'type' => 'room', 'capacity' => 1]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->putJson("/api/resources/{$resource->id}", ['name' => 'Hacked']);

        $response->assertStatus(403);
    }

    public function test_reviewer_cannot_delete_resource(): void
    {
        $resource = Resource::create(['name' => 'Room', 'type' => 'room', 'capacity' => 1]);

        $response = $this->withHeaders($this->reviewerAuth())
            ->deleteJson("/api/resources/{$resource->id}");

        $response->assertStatus(403);
    }
}
