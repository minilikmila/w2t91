<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Resources\ApprovalResource;
use App\Http\Resources\EnrollmentResource;
use App\Jobs\ProcessEnrollmentApproval;
use App\Models\Approval;
use App\Models\Enrollment;
use App\Services\EnrollmentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ApprovalController extends Controller
{
    use AuthorizesRecordAccess;
    private EnrollmentWorkflowService $workflowService;

    public function __construct(EnrollmentWorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * List approvals, optionally filtered by enrollment or status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Approval::with(['enrollment.learner', 'reviewer']);

        // Scope results based on user role
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('planner')) {
            $query->where(function ($q) use ($user) {
                $q->where('reviewer_id', $user->id)
                    ->orWhereNull('reviewer_id');
            });
        }

        if ($request->has('enrollment_id')) {
            $query->where('enrollment_id', $request->enrollment_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('reviewer_id')) {
            $query->where('reviewer_id', $request->reviewer_id);
        }

        // Show pending approvals for the current reviewer by default
        if ($request->has('my_queue') && $request->my_queue === 'true') {
            $query->where(function ($q) use ($request) {
                $q->where('reviewer_id', $request->user()->id)
                    ->orWhereNull('reviewer_id');
            })->whereIn('status', ['pending', 'in_review']);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return ApprovalResource::collection(
            $query->orderBy('created_at', 'desc')->paginate($perPage)
        );
    }

    /**
     * Show a single approval with enrollment details.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $approval = Approval::with(['enrollment.learner', 'reviewer'])->findOrFail($id);
        $this->authorizeRecord($request, $approval);

        return response()->json(['data' => new ApprovalResource($approval)]);
    }

    /**
     * Submit an approval decision (approve or reject).
     * Dispatches processing to the queue.
     */
    public function decide(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'comments' => 'nullable|string|max:2000',
            'reason_code' => 'nullable|string|max:100',
        ]);

        $approval = Approval::with('enrollment')->findOrFail($id);
        $this->authorizeRecord($request, $approval);
        $enrollment = $approval->enrollment;

        if (!$approval->isPending()) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'This approval has already been decided.',
            ], 422);
        }

        if ($enrollment->status !== Enrollment::STATUS_UNDER_REVIEW) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'The enrollment is not currently in review.',
            ], 422);
        }

        // Validate role requirement before dispatching to queue
        if ($request->decision === 'approved') {
            try {
                $this->workflowService->validateApprovalRole($enrollment, $request->user());
            } catch (\InvalidArgumentException $e) {
                return response()->json([
                    'error' => 'Authorization Error',
                    'message' => $e->getMessage(),
                ], 403);
            }
        }

        // Dispatch to queue for processing
        ProcessEnrollmentApproval::dispatch(
            $enrollment->id,
            $request->user()->id,
            $request->decision,
            $request->comments,
            $request->reason_code
        );

        return response()->json([
            'message' => 'Approval decision queued for processing.',
            'approval_id' => $approval->id,
            'enrollment_id' => $enrollment->id,
            'decision' => $request->decision,
        ], 202);
    }

    /**
     * Directly process an approval decision (synchronous).
     */
    public function decideSync(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'decision' => 'required|string|in:approved,rejected',
            'comments' => 'nullable|string|max:2000',
            'reason_code' => 'nullable|string|max:100',
        ]);

        $approval = Approval::with('enrollment')->findOrFail($id);
        $this->authorizeRecord($request, $approval);
        $enrollment = $approval->enrollment;

        if (!$approval->isPending()) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'This approval has already been decided.',
            ], 422);
        }

        if ($enrollment->status !== Enrollment::STATUS_UNDER_REVIEW) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'The enrollment is not currently in review.',
            ], 422);
        }

        try {
            $enrollment = $this->workflowService->processApproval(
                $enrollment,
                $request->user(),
                $request->decision,
                $request->comments,
                $request->reason_code
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Processing Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $enrollment->load(['learner', 'approvals', 'lastActor']);

        return response()->json([
            'message' => "Approval decision '{$request->decision}' processed.",
            'data' => new EnrollmentResource($enrollment),
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    /**
     * Claim a pending approval for review (assign to current user).
     */
    public function claim(Request $request, int $id): JsonResponse
    {
        $approval = Approval::findOrFail($id);
        $this->authorizeRecord($request, $approval);

        if (!$approval->isPending()) {
            return response()->json([
                'error' => 'Invalid State',
                'message' => 'This approval cannot be claimed.',
            ], 422);
        }

        $approval->update([
            'reviewer_id' => $request->user()->id,
            'status' => 'in_review',
        ]);

        $approval->load(['enrollment.learner', 'reviewer']);

        return response()->json([
            'message' => 'Approval claimed for review.',
            'data' => new ApprovalResource($approval),
        ]);
    }
}
