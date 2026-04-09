<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\User;

class RefundService
{
    private EnrollmentWorkflowService $workflowService;

    public function __construct(EnrollmentWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Check if a cancelled enrollment is eligible for a refund.
     */
    public function isEligibleForRefund(Enrollment $enrollment): array
    {
        $reasons = [];

        if ($enrollment->status !== Enrollment::STATUS_CANCELLED) {
            $reasons[] = 'Enrollment must be in cancelled status to request a refund.';
        }

        if (!$enrollment->payment_received) {
            $reasons[] = 'No payment has been recorded for this enrollment.';
        }

        if ($enrollment->payment_amount === null || (float) $enrollment->payment_amount <= 0) {
            $reasons[] = 'No payment amount is associated with this enrollment.';
        }

        if ($enrollment->refund_cutoff_at && $enrollment->cancelled_at) {
            if ($enrollment->cancelled_at->isAfter($enrollment->refund_cutoff_at)) {
                $reasons[] = 'Cancellation occurred after the refund cutoff date (' . $enrollment->refund_cutoff_at->toDateString() . ').';
            }
        }

        if ($enrollment->refunded_at) {
            $reasons[] = 'This enrollment has already been refunded.';
        }

        return [
            'eligible' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Process a refund for a cancelled enrollment.
     */
    public function processRefund(Enrollment $enrollment, User $actor, ?string $reason = null): Enrollment
    {
        $eligibility = $this->isEligibleForRefund($enrollment);

        if (!$eligibility['eligible']) {
            throw new \InvalidArgumentException(
                'Refund not eligible: ' . implode(' ', $eligibility['reasons'])
            );
        }

        return $this->workflowService->transition(
            $enrollment,
            Enrollment::STATUS_REFUNDED,
            $actor,
            'refund_processed',
            $reason ?? 'Refund processed for cancelled enrollment.'
        );
    }

    /**
     * Cancel an enrollment and check immediate refund eligibility.
     */
    public function cancelWithRefundCheck(Enrollment $enrollment, User $actor, ?string $reason = null): array
    {
        $enrollment = $this->workflowService->cancel($enrollment, $actor, $reason);

        // Mark payment_received if needed for refund eligibility check
        $eligibility = $this->isEligibleForRefund($enrollment);

        return [
            'enrollment' => $enrollment,
            'refund_eligible' => $eligibility['eligible'],
            'refund_reasons' => $eligibility['reasons'],
        ];
    }
}
