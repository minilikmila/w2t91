<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Booking;
use App\Models\Enrollment;
use App\Models\FieldPlacement;
use App\Models\Learner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'total_learners' => Learner::count(),
                'active_enrollments' => Enrollment::where('status', Enrollment::STATUS_ENROLLED)->count(),
                'confirmed_bookings' => Booking::where('status', Booking::STATUS_CONFIRMED)->count(),
                'active_placements' => FieldPlacement::where('status', FieldPlacement::STATUS_ACTIVE)->count(),
                'pending_approvals' => Approval::where('status', 'pending')->count(),
            ],
        ]);
    }

    public function enrollmentMetrics(Request $request): JsonResponse
    {
        if ($request->has('date_from') || $request->has('date_to')) {
            $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);
        }

        $query = Enrollment::query();

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (clone $query)->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'by_status' => $byStatus,
            ],
        ]);
    }

    public function bookingMetrics(Request $request): JsonResponse
    {
        if ($request->has('date_from') || $request->has('date_to')) {
            $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);
        }

        if ($request->has('resource_id')) {
            $request->validate([
                'resource_id' => 'integer|exists:resources,id',
            ]);
        }

        $query = Booking::query();

        if ($request->has('date_from')) {
            $query->where('start_time', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('start_time', '<=', $request->date_to);
        }

        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        $byStatus = (clone $query)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $total = (clone $query)->count();

        return response()->json([
            'data' => [
                'total' => $total,
                'by_status' => $byStatus,
            ],
        ]);
    }

    public function placementMetrics(): JsonResponse
    {
        $byStatus = FieldPlacement::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $byLocation = FieldPlacement::query()
            ->selectRaw('location_id, count(*) as count')
            ->groupBy('location_id')
            ->pluck('count', 'location_id');

        $total = FieldPlacement::count();

        return response()->json([
            'data' => [
                'total' => $total,
                'by_status' => $byStatus,
                'by_location' => $byLocation,
            ],
        ]);
    }

    public function operationalSummary(): JsonResponse
    {
        $enrollmentPipeline = Enrollment::query()
            ->selectRaw('status, count(*) as count')
            ->whereIn('status', [
                Enrollment::STATUS_DRAFT,
                Enrollment::STATUS_SUBMITTED,
                Enrollment::STATUS_UNDER_REVIEW,
                Enrollment::STATUS_APPROVED,
                Enrollment::STATUS_ENROLLED,
                Enrollment::STATUS_WAITLISTED,
            ])
            ->groupBy('status')
            ->pluck('count', 'status');

        $bookingUtilization = [
            'provisional' => Booking::where('status', Booking::STATUS_PROVISIONAL)->count(),
            'confirmed' => Booking::where('status', Booking::STATUS_CONFIRMED)->count(),
            'completed' => Booking::where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => Booking::where('status', Booking::STATUS_CANCELLED)->count(),
            'no_show' => Booking::where('status', Booking::STATUS_NO_SHOW)->count(),
        ];

        $placementCoverage = [
            'active' => FieldPlacement::where('status', FieldPlacement::STATUS_ACTIVE)->count(),
            'pending' => FieldPlacement::where('status', FieldPlacement::STATUS_PENDING)->count(),
            'completed' => FieldPlacement::where('status', FieldPlacement::STATUS_COMPLETED)->count(),
        ];

        $pendingApprovalQueue = Approval::where('status', 'pending')->count();
        $inReviewApprovalQueue = Approval::where('status', 'in_review')->count();

        return response()->json([
            'data' => [
                'enrollment_pipeline' => $enrollmentPipeline,
                'booking_utilization' => $bookingUtilization,
                'placement_coverage' => $placementCoverage,
                'pending_approval_queue' => [
                    'pending' => $pendingApprovalQueue,
                    'in_review' => $inReviewApprovalQueue,
                    'total' => $pendingApprovalQueue + $inReviewApprovalQueue,
                ],
            ],
        ]);
    }
}
