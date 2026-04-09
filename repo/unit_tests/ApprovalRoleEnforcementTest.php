<?php

namespace Tests\Unit;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\EnrollmentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalRoleEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private EnrollmentWorkflowService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnrollmentWorkflowService::class);
    }

    private function createUserWithRole(string $roleSlug): User
    {
        $role = Role::firstOrCreate(
            ['slug' => $roleSlug],
            ['name' => ucfirst($roleSlug)]
        );

        $perm = Permission::firstOrCreate(
            ['slug' => 'enrollments.approve'],
            ['name' => 'Approve Enrollments']
        );
        if (!$role->permissions()->where('slug', 'enrollments.approve')->exists()) {
            $role->permissions()->attach($perm);
        }

        return User::create([
            'username' => $roleSlug . '_' . uniqid(),
            'name' => ucfirst($roleSlug) . ' User',
            'email' => $roleSlug . '_' . uniqid() . '@test.com',
            'password' => 'TestPass123!@',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function createMinorEnrollmentUnderReview(User $reviewer): Enrollment
    {
        $learner = Learner::create([
            'first_name' => 'Minor',
            'last_name' => 'Learner',
            'date_of_birth' => now()->subYears(15)->toDateString(),
            'guardian_contact' => '+1234567890',
            'guardian_name' => 'Guardian Name',
        ]);

        $enrollment = $this->service->createEnrollment(
            ['program_name' => 'Test Program'],
            $learner,
            2
        );

        // Transition through to under_review
        $enrollment = $this->service->submitForReview($enrollment, $reviewer);
        $enrollment = $this->service->beginReview($enrollment, $reviewer);

        return $enrollment;
    }

    public function test_reviewer_can_approve_level_1(): void
    {
        $reviewer = $this->createUserWithRole('reviewer');
        $enrollment = $this->createMinorEnrollmentUnderReview($reviewer);

        $this->assertEquals(1, $enrollment->current_approval_level);

        // Reviewer should be able to approve level 1 (required_role = 'reviewer')
        $result = $this->service->processApproval($enrollment, $reviewer, 'approved', 'Looks good');

        $this->assertEquals(2, $result->current_approval_level);
    }

    public function test_reviewer_cannot_approve_level_2_for_minor(): void
    {
        $reviewer = $this->createUserWithRole('reviewer');
        $enrollment = $this->createMinorEnrollmentUnderReview($reviewer);

        // Approve level 1 first
        $enrollment = $this->service->processApproval($enrollment, $reviewer, 'approved');

        $this->assertEquals(2, $enrollment->current_approval_level);

        // Level 2 for minor requires 'admin' role — reviewer should be rejected
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("requires the 'admin' role");

        $this->service->processApproval($enrollment, $reviewer, 'approved');
    }

    public function test_admin_can_approve_level_2_for_minor(): void
    {
        $reviewer = $this->createUserWithRole('reviewer');
        $admin = $this->createUserWithRole('admin');
        $enrollment = $this->createMinorEnrollmentUnderReview($reviewer);

        // Level 1 approved by reviewer
        $enrollment = $this->service->processApproval($enrollment, $reviewer, 'approved');

        $this->assertEquals(2, $enrollment->current_approval_level);

        // Level 2 approved by admin (required_role = 'admin')
        $result = $this->service->processApproval($enrollment, $admin, 'approved');

        $this->assertEquals(Enrollment::STATUS_APPROVED, $result->status);
    }

    public function test_reviewer_can_reject_at_any_level(): void
    {
        $reviewer = $this->createUserWithRole('reviewer');
        $enrollment = $this->createMinorEnrollmentUnderReview($reviewer);

        // Approve level 1
        $enrollment = $this->service->processApproval($enrollment, $reviewer, 'approved');

        // Reviewer should still be able to reject at level 2 even if they can't approve
        // (rejecting does not require the elevated role)
        $result = $this->service->processApproval($enrollment, $reviewer, 'rejected', 'Not suitable');

        $this->assertEquals(Enrollment::STATUS_REJECTED, $result->status);
    }

    public function test_workflow_metadata_contains_level_config(): void
    {
        $learner = Learner::create([
            'first_name' => 'Test',
            'last_name' => 'Minor',
            'date_of_birth' => now()->subYears(16)->toDateString(),
            'guardian_contact' => '+1234567890',
            'guardian_name' => 'Guardian',
        ]);

        $metadata = ApprovalWorkflow::buildDefault($learner, 2);

        $this->assertArrayHasKey('level_config', $metadata);
        $this->assertCount(2, $metadata['level_config']);
        $this->assertEquals('reviewer', $metadata['level_config'][0]['required_role']);
        $this->assertEquals('admin', $metadata['level_config'][1]['required_role']);
    }

    public function test_non_minor_workflow_does_not_require_admin(): void
    {
        $learner = Learner::create([
            'first_name' => 'Adult',
            'last_name' => 'Learner',
            'date_of_birth' => now()->subYears(25)->toDateString(),
        ]);

        $metadata = ApprovalWorkflow::buildDefault($learner, 1);

        $this->assertCount(1, $metadata['level_config']);
        $this->assertEquals('reviewer', $metadata['level_config'][0]['required_role']);
    }
}
