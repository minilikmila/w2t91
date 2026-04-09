<?php

namespace App\Jobs;

use App\Models\Approval;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\EnrollmentWorkflowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEnrollmentApproval implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private int $enrollmentId,
        private int $reviewerId,
        private string $decision,
        private ?string $comments = null,
        private ?string $reasonCode = null,
    ) {}

    public function handle(EnrollmentWorkflowService $workflowService): void
    {
        $enrollment = Enrollment::find($this->enrollmentId);

        if (!$enrollment) {
            Log::warning("ProcessEnrollmentApproval: Enrollment {$this->enrollmentId} not found.");
            return;
        }

        if ($enrollment->status !== Enrollment::STATUS_UNDER_REVIEW) {
            Log::warning("ProcessEnrollmentApproval: Enrollment {$this->enrollmentId} is not in review (status: {$enrollment->status}).");
            return;
        }

        $reviewer = User::find($this->reviewerId);

        if (!$reviewer) {
            Log::warning("ProcessEnrollmentApproval: Reviewer {$this->reviewerId} not found.");
            return;
        }

        try {
            $workflowService->processApproval(
                $enrollment,
                $reviewer,
                $this->decision,
                $this->comments,
                $this->reasonCode
            );

            Log::info("ProcessEnrollmentApproval: Enrollment {$this->enrollmentId} processed with decision '{$this->decision}' by reviewer {$this->reviewerId}.");
        } catch (\Exception $e) {
            Log::error("ProcessEnrollmentApproval: Failed to process enrollment {$this->enrollmentId}: {$e->getMessage()}");
            throw $e;
        }
    }
}
