<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\ApprovalWorkflow;
use App\Models\Enrollment;
use App\Models\EnrollmentTransition;
use App\Models\Learner;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EnrollmentWorkflowService
{
    /**
     * Create a new enrollment in draft status with workflow metadata.
     */
    public function createEnrollment(array $data, Learner $learner, int $approvalLevels = 1): Enrollment
    {
        $workflowMetadata = ApprovalWorkflow::buildDefault($learner, $approvalLevels);

        $enrollment = Enrollment::create([
            'learner_id' => $learner->id,
            'program_name' => $data['program_name'],
            'status' => Enrollment::STATUS_DRAFT,
            'max_approval_levels' => $workflowMetadata['levels'],
            'workflow_metadata' => $workflowMetadata,
            'requires_guardian_approval' => $workflowMetadata['requires_guardian_approval'],
            'payment_amount' => $data['payment_amount'] ?? null,
            'refund_cutoff_at' => $data['refund_cutoff_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'last_actor_id' => $data['actor_id'] ?? null,
        ]);

        return $enrollment;
    }

    /**
     * Transition an enrollment to a new status with validation.
     */
    public function transition(Enrollment $enrollment, string $newStatus, User $actor, ?string $reasonCode = null, ?string $notes = null): Enrollment
    {
        if (!$enrollment->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException(
                "Cannot transition from '{$enrollment->status}' to '{$newStatus}'. " .
                'Allowed transitions: ' . implode(', ', $enrollment->getAllowedTransitions())
            );
        }

        // Check workflow blockers for advancement transitions
        if (in_array($newStatus, [Enrollment::STATUS_UNDER_REVIEW, Enrollment::STATUS_APPROVED])) {
            $learner = $enrollment->learner;
            $check = ApprovalWorkflow::canAdvance($enrollment->workflow_metadata ?? [], $learner);

            if (!$check['can_advance']) {
                throw new \InvalidArgumentException(
                    'Cannot advance enrollment: ' . implode('; ', $check['blockers'])
                );
            }
        }

        return DB::transaction(function () use ($enrollment, $newStatus, $actor, $reasonCode, $notes) {
            $previousStatus = $enrollment->status;

            $updateData = [
                'previous_status' => $previousStatus,
                'status' => $newStatus,
                'last_actor_id' => $actor->id,
                'reason_code' => $reasonCode ?? $enrollment->reason_code,
            ];

            if ($notes) {
                $updateData['notes'] = $notes;
            }

            // Set timestamps based on transition
            switch ($newStatus) {
                case Enrollment::STATUS_ENROLLED:
                    $updateData['enrolled_at'] = now();
                    break;
                case Enrollment::STATUS_COMPLETED:
                    $updateData['completed_at'] = now();
                    break;
                case Enrollment::STATUS_CANCELLED:
                    $updateData['cancelled_at'] = now();
                    break;
                case Enrollment::STATUS_REFUNDED:
                    $updateData['refunded_at'] = now();
                    break;
            }

            $enrollment->update($updateData);

            // Record immutable transition history
            EnrollmentTransition::create([
                'enrollment_id' => $enrollment->id,
                'from_status' => $previousStatus,
                'to_status' => $newStatus,
                'actor_id' => $actor->id,
                'reason_code' => $reasonCode,
                'notes' => $notes,
                'created_at' => now(),
            ]);

            return $enrollment->fresh();
        });
    }

    /**
     * Submit an enrollment for review (draft → submitted).
     */
    public function submitForReview(Enrollment $enrollment, User $actor): Enrollment
    {
        return $this->transition($enrollment, Enrollment::STATUS_SUBMITTED, $actor, 'submitted_for_review');
    }

    /**
     * Begin review of an enrollment (submitted → under_review).
     * Creates the first approval record.
     */
    public function beginReview(Enrollment $enrollment, User $reviewer): Enrollment
    {
        $enrollment = $this->transition($enrollment, Enrollment::STATUS_UNDER_REVIEW, $reviewer, 'review_started');

        // Create first-level approval record
        Approval::create([
            'enrollment_id' => $enrollment->id,
            'reviewer_id' => $reviewer->id,
            'level' => 1,
            'status' => 'in_review',
        ]);

        $enrollment->update(['current_approval_level' => 1]);

        return $enrollment->fresh();
    }

    /**
     * Process an approval decision at the current level.
     */
    public function processApproval(Enrollment $enrollment, User $reviewer, string $decision, ?string $comments = null, ?string $reasonCode = null): Enrollment
    {
        if (!in_array($decision, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException("Decision must be 'approved' or 'rejected'.");
        }

        $currentLevel = $enrollment->current_approval_level;

        // Find or create the approval for the current level
        $approval = Approval::where('enrollment_id', $enrollment->id)
            ->where('level', $currentLevel)
            ->first();

        if (!$approval) {
            $approval = Approval::create([
                'enrollment_id' => $enrollment->id,
                'reviewer_id' => $reviewer->id,
                'level' => $currentLevel,
                'status' => 'in_review',
            ]);
        }

        // Record the decision
        $approval->update([
            'reviewer_id' => $reviewer->id,
            'status' => $decision,
            'decision' => $decision,
            'comments' => $comments,
            'reason_code' => $reasonCode,
            'decided_at' => now(),
        ]);

        if ($decision === 'rejected') {
            return $this->transition($enrollment, Enrollment::STATUS_REJECTED, $reviewer, $reasonCode ?? 'rejected', $comments);
        }

        // Approved — check if more levels needed
        if ($currentLevel < $enrollment->max_approval_levels) {
            // Advance to next level
            $nextLevel = $currentLevel + 1;
            $enrollment->update(['current_approval_level' => $nextLevel]);

            // Create next-level approval record
            Approval::create([
                'enrollment_id' => $enrollment->id,
                'level' => $nextLevel,
                'status' => 'pending',
            ]);

            return $enrollment->fresh();
        }

        // All levels approved
        return $this->transition($enrollment, Enrollment::STATUS_APPROVED, $reviewer, $reasonCode ?? 'fully_approved', $comments);
    }

    /**
     * Enroll an approved enrollment.
     */
    public function enroll(Enrollment $enrollment, User $actor): Enrollment
    {
        return $this->transition($enrollment, Enrollment::STATUS_ENROLLED, $actor, 'enrolled');
    }

    /**
     * Waitlist an approved enrollment.
     */
    public function waitlist(Enrollment $enrollment, User $actor): Enrollment
    {
        return $this->transition($enrollment, Enrollment::STATUS_WAITLISTED, $actor, 'waitlisted');
    }

    /**
     * Complete an enrollment.
     */
    public function complete(Enrollment $enrollment, User $actor): Enrollment
    {
        return $this->transition($enrollment, Enrollment::STATUS_COMPLETED, $actor, 'completed');
    }

    /**
     * Cancel an enrollment.
     */
    public function cancel(Enrollment $enrollment, User $actor, ?string $reason = null): Enrollment
    {
        return $this->transition($enrollment, Enrollment::STATUS_CANCELLED, $actor, 'cancelled', $reason);
    }

    /**
     * Get the current workflow status summary.
     */
    public function getWorkflowStatus(Enrollment $enrollment): array
    {
        $approvals = $enrollment->approvals()->orderBy('level')->get();

        return [
            'enrollment_id' => $enrollment->id,
            'status' => $enrollment->status,
            'previous_status' => $enrollment->previous_status,
            'current_approval_level' => $enrollment->current_approval_level,
            'max_approval_levels' => $enrollment->max_approval_levels,
            'requires_guardian_approval' => $enrollment->requires_guardian_approval,
            'allowed_transitions' => $enrollment->getAllowedTransitions(),
            'approvals' => $approvals->map(fn ($a) => [
                'level' => $a->level,
                'status' => $a->status,
                'reviewer_id' => $a->reviewer_id,
                'decision' => $a->decision,
                'comments' => $a->comments,
                'decided_at' => $a->decided_at?->toIso8601String(),
            ])->toArray(),
            'workflow_metadata' => $enrollment->workflow_metadata,
        ];
    }
}
