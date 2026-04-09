<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Approval;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $adminUser;
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

        $this->adminUser = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'appr-token-padded-to-exactly-sixty-four-characters-long-here-ok';
        ApiToken::create([
            'user_id' => $this->adminUser->id,
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

    public function test_approval_sync_approve_flow(): void
    {
        // Create enrollment
        $createResponse = $this->withHeaders($this->auth())
            ->postJson('/api/enrollments', [
                'learner_id' => $this->learner->id,
                'program_name' => 'Approval Test Program',
            ]);
        $enrollmentId = $createResponse->json('data.id');

        // Submit
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollmentId}/submit");

        // Begin review
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollmentId}/review");

        // Get the approval record
        $approval = Approval::where('enrollment_id', $enrollmentId)->first();

        // Decide sync - approve
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/approvals/{$approval->id}/decide-sync", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(200);
    }

    public function test_approval_sync_reject_flow(): void
    {
        // Create enrollment
        $createResponse = $this->withHeaders($this->auth())
            ->postJson('/api/enrollments', [
                'learner_id' => $this->learner->id,
                'program_name' => 'Rejection Test Program',
            ]);
        $enrollmentId = $createResponse->json('data.id');

        // Submit
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollmentId}/submit");

        // Begin review
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollmentId}/review");

        // Get the approval record
        $approval = Approval::where('enrollment_id', $enrollmentId)->first();

        // Decide sync - reject
        $response = $this->withHeaders($this->auth())
            ->postJson("/api/approvals/{$approval->id}/decide-sync", [
                'decision' => 'rejected',
            ]);

        $response->assertStatus(200);

        $enrollment = Enrollment::find($enrollmentId);
        $this->assertEquals('rejected', $enrollment->status);
    }

    public function test_approval_queue_dispatches_job(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Queue Test',
            'status' => Enrollment::STATUS_UNDER_REVIEW,
            'current_approval_level' => 1,
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $approval = Approval::create([
            'enrollment_id' => $enrollment->id,
            'level' => 1,
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/approvals/{$approval->id}/decide", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(202);
    }

    public function test_claim_approval(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Claim Test',
            'status' => Enrollment::STATUS_UNDER_REVIEW,
            'current_approval_level' => 1,
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $approval = Approval::create([
            'enrollment_id' => $enrollment->id,
            'level' => 1,
            'status' => 'pending',
            'reviewer_id' => null,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/approvals/{$approval->id}/claim");

        $response->assertStatus(200);

        $approval->refresh();
        $this->assertEquals($this->adminUser->id, $approval->reviewer_id);
    }

    public function test_already_decided_approval_returns_422(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Already Decided Test',
            'status' => Enrollment::STATUS_UNDER_REVIEW,
            'current_approval_level' => 1,
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $approval = Approval::create([
            'enrollment_id' => $enrollment->id,
            'level' => 1,
            'status' => 'approved',
            'decision' => 'approved',
            'decided_at' => now(),
            'reviewer_id' => $this->adminUser->id,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/approvals/{$approval->id}/decide-sync", [
                'decision' => 'approved',
            ]);

        $response->assertStatus(422);
    }
}
