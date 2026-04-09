<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnrollmentApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $perms = ['enrollments.create', 'enrollments.view', 'enrollments.update', 'enrollments.approve', 'enrollments.cancel'];
        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'enroll-token-padded-to-exactly-sixty-four-characters-long-here!';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create([
            'first_name' => 'John', 'last_name' => 'Doe',
            'date_of_birth' => '2000-05-15',
        ]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_create_enrollment_draft(): void
    {
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/enrollments', [
                'learner_id' => $this->learner->id,
                'program_name' => 'Test Program',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.program_name', 'Test Program');
    }

    public function test_create_enrollment_for_minor_requires_guardian(): void
    {
        $minor = Learner::create([
            'first_name' => 'Young',
            'last_name' => 'Learner',
            'date_of_birth' => now()->subYears(15)->format('Y-m-d'),
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/enrollments', [
                'learner_id' => $minor->id,
                'program_name' => 'Youth Program',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.requires_guardian_approval', true)
            ->assertJsonPath('workflow.requires_guardian_approval', true);
    }

    public function test_enrollment_submit_for_review(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/submit");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_enrollment_invalid_transition_rejected(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'max_approval_levels' => 1,
        ]);

        $response = $this->withHeaders($this->auth())
            ->putJson("/api/enrollments/{$enrollment->id}/transition", [
                'status' => 'enrolled',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Invalid Transition');
    }

    public function test_list_enrollments(): void
    {
        Enrollment::create([
            'learner_id' => $this->learner->id, 'program_name' => 'A', 'status' => 'draft',
        ]);
        Enrollment::create([
            'learner_id' => $this->learner->id, 'program_name' => 'B', 'status' => 'enrolled',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/enrollments');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_filter_enrollments_by_status(): void
    {
        Enrollment::create([
            'learner_id' => $this->learner->id, 'program_name' => 'A', 'status' => 'draft',
        ]);
        Enrollment::create([
            'learner_id' => $this->learner->id, 'program_name' => 'B', 'status' => 'enrolled',
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/enrollments?status=draft');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_workflow_status_endpoint(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'max_approval_levels' => 2,
            'workflow_metadata' => ['levels' => 2, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/enrollments/{$enrollment->id}/workflow");

        $response->assertStatus(200)
            ->assertJsonPath('status', 'draft')
            ->assertJsonPath('max_approval_levels', 2);
    }

    public function test_cancel_enrollment(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'enrolled',
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/cancel", [
                'reason' => 'Student withdrew',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_refund_eligibility_check(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'cancelled',
            'payment_amount' => 500.00,
            'payment_received' => true,
            'cancelled_at' => now(),
            'refund_cutoff_at' => now()->addDays(30),
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson("/api/enrollments/{$enrollment->id}/refund-eligibility");

        $response->assertStatus(200)
            ->assertJsonPath('eligible', true);
    }
}
