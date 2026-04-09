<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Requests\EnrollmentRequest;
use App\Models\Enrollment;
use App\Models\Learner;
use App\Services\EnrollmentWorkflowService;
use App\Services\RefundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use AuthorizesRecordAccess;
    private EnrollmentWorkflowService $workflowService;
    private RefundService $refundService;

    public function __construct(EnrollmentWorkflowService $workflowService, RefundService $refundService)
    {
        $this->workflowService = $workflowService;
        $this->refundService = $refundService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::with(['learner', 'approvals', 'lastActor']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('learner_id')) {
            $query->where('learner_id', $request->learner_id);
        }

        if ($request->has('program_name')) {
            $query->where('program_name', 'like', "%{$request->program_name}%");
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }

    public function store(EnrollmentRequest $request): JsonResponse
    {
        $learner = Learner::findOrFail($request->learner_id);

        $enrollment = $this->workflowService->createEnrollment(
            $request->validated(),
            $learner,
            $request->get('approval_levels', 1)
        );

        $enrollment->load(['learner', 'approvals']);

        return response()->json([
            'message' => 'Enrollment created in draft status.',
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $enrollment = Enrollment::with(['learner', 'approvals', 'lastActor'])->findOrFail($id);
        $this->authorizeRecord($request, $enrollment);

        return response()->json([
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    public function transition(EnrollmentRequest $request, int $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $this->authorizeMutation($request, $enrollment);
        $user = $request->user();
        $newStatus = $request->status;

        try {
            $enrollment = $this->workflowService->transition(
                $enrollment,
                $newStatus,
                $user,
                $request->reason_code,
                $request->notes
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        $enrollment->load(['learner', 'approvals', 'lastActor']);

        return response()->json([
            'message' => "Enrollment transitioned to '{$newStatus}'.",
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    public function submitForReview(int $id, Request $request): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $this->authorizeMutation($request, $enrollment);

        try {
            $enrollment = $this->workflowService->submitForReview($enrollment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Enrollment submitted for review.',
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    public function beginReview(int $id, Request $request): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);
        $this->authorizeRecord($request, $enrollment);

        try {
            $enrollment = $this->workflowService->beginReview($enrollment, $request->user());
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'message' => 'Review started.',
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    public function workflowStatus(Request $request, int $id): JsonResponse
    {
        $enrollment = Enrollment::with(['learner', 'approvals'])->findOrFail($id);
        $this->authorizeRecord($request, $enrollment);

        return response()->json($this->workflowService->getWorkflowStatus($enrollment));
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $enrollment = Enrollment::findOrFail($id);
        $this->authorizeMutation($request, $enrollment);

        try {
            $result = $this->refundService->cancelWithRefundCheck(
                $enrollment,
                $request->user(),
                $request->reason
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Invalid Transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        $result['enrollment']->load(['learner', 'approvals', 'lastActor']);

        return response()->json([
            'message' => 'Enrollment cancelled.',
            'data' => $result['enrollment'],
            'refund_eligible' => $result['refund_eligible'],
            'refund_reasons' => $result['refund_reasons'],
            'workflow' => $this->workflowService->getWorkflowStatus($result['enrollment']),
        ]);
    }

    public function refund(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:2000',
        ]);

        $enrollment = Enrollment::findOrFail($id);
        $this->authorizeMutation($request, $enrollment);

        // Check eligibility first
        $eligibility = $this->refundService->isEligibleForRefund($enrollment);

        if (!$eligibility['eligible']) {
            return response()->json([
                'error' => 'Refund Not Eligible',
                'message' => 'This enrollment is not eligible for a refund.',
                'reasons' => $eligibility['reasons'],
            ], 422);
        }

        try {
            $enrollment = $this->refundService->processRefund(
                $enrollment,
                $request->user(),
                $request->reason
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Refund Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $enrollment->load(['learner', 'approvals', 'lastActor']);

        return response()->json([
            'message' => 'Refund processed successfully.',
            'data' => $enrollment,
            'workflow' => $this->workflowService->getWorkflowStatus($enrollment),
        ]);
    }

    public function refundEligibility(int $id): JsonResponse
    {
        $enrollment = Enrollment::findOrFail($id);

        $eligibility = $this->refundService->isEligibleForRefund($enrollment);

        return response()->json([
            'enrollment_id' => $enrollment->id,
            'status' => $enrollment->status,
            'payment_amount' => $enrollment->payment_amount,
            'payment_received' => $enrollment->payment_received,
            'refund_cutoff_at' => $enrollment->refund_cutoff_at?->toIso8601String(),
            'cancelled_at' => $enrollment->cancelled_at?->toIso8601String(),
            'eligible' => $eligibility['eligible'],
            'reasons' => $eligibility['reasons'],
        ]);
    }
}
