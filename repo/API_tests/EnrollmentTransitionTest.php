<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Enrollment;
use App\Models\EnrollmentTransition;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnrollmentTransitionTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Learner $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        foreach (['enrollments.create', 'enrollments.view', 'enrollments.update', 'enrollments.approve', 'enrollments.cancel'] as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'trans-token-padded-to-exactly-sixty-four-characters-long-here!!';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create(['first_name' => 'John', 'last_name' => 'Doe', 'date_of_birth' => '2000-05-15']);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_submit_creates_transition_record(): void
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

        $response->assertStatus(200);

        $this->assertDatabaseHas('enrollment_transitions', [
            'enrollment_id' => $enrollment->id,
            'from_status' => 'draft',
            'to_status' => 'submitted',
        ]);
    }

    public function test_cancel_creates_transition_record(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'enrolled',
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/cancel", ['reason' => 'Withdrawal']);

        $response->assertStatus(200);

        $this->assertDatabaseHas('enrollment_transitions', [
            'enrollment_id' => $enrollment->id,
            'from_status' => 'enrolled',
            'to_status' => 'cancelled',
        ]);
    }

    public function test_transition_record_has_actor_and_reason(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/submit");

        $transition = EnrollmentTransition::where('enrollment_id', $enrollment->id)->first();

        $this->assertNotNull($transition);
        $this->assertNotNull($transition->actor_id);
        $this->assertNotNull($transition->reason_code);
        $this->assertNotNull($transition->created_at);
    }

    public function test_multiple_transitions_create_full_history(): void
    {
        $enrollment = Enrollment::create([
            'learner_id' => $this->learner->id,
            'program_name' => 'Test',
            'status' => 'draft',
            'max_approval_levels' => 1,
            'workflow_metadata' => ['levels' => 1, 'conditions' => []],
        ]);

        // draft → submitted
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/submit");

        // submitted → under_review
        $this->withHeaders($this->auth())
            ->postJson("/api/enrollments/{$enrollment->id}/review");

        $transitions = EnrollmentTransition::where('enrollment_id', $enrollment->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $transitions);
        $this->assertEquals('draft', $transitions[0]->from_status);
        $this->assertEquals('submitted', $transitions[0]->to_status);
        $this->assertEquals('submitted', $transitions[1]->from_status);
        $this->assertEquals('under_review', $transitions[1]->to_status);
    }
}
