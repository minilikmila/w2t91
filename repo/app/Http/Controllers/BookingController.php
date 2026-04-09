<?php

namespace App\Http\Controllers;

use App\Http\Requests\BookingRequest;
use App\Models\Booking;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    private BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Booking::with(['resource', 'learner', 'bookedByUser']);

        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->has('learner_id')) {
            $query->where('learner_id', $request->learner_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('start_time', $request->date);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('start_time', 'asc')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'learner_id' => 'required|exists:learners,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $booking = $this->bookingService->createProvisionalHold([
                'resource_id' => $request->resource_id,
                'learner_id' => $request->learner_id,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'idempotency_key' => $request->idempotency_key,
                'booked_by' => $request->user()->id,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Booking Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Provisional hold created. Confirm within 5 minutes.',
            'data' => $booking,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $booking = Booking::with(['resource', 'learner', 'bookedByUser'])->findOrFail($id);

        return response()->json(['data' => $booking]);
    }

    public function confirm(int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->bookingService->confirmBooking($booking);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Confirmation Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Booking confirmed.',
            'data' => $booking,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->bookingService->cancelBooking($booking, $request->notes);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Cancellation Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Booking cancelled.',
            'data' => $booking,
        ]);
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $booking = Booking::findOrFail($id);

        try {
            $booking = $this->bookingService->rescheduleBooking(
                $booking,
                Carbon::parse($request->start_time),
                Carbon::parse($request->end_time)
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Reschedule Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Booking rescheduled.',
            'data' => $booking,
        ]);
    }

    public function waitlist(Request $request): JsonResponse
    {
        $request->validate([
            'resource_id' => 'required|exists:resources,id',
            'learner_id' => 'required|exists:learners,id',
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $entry = $this->bookingService->addToWaitlist([
            'resource_id' => $request->resource_id,
            'learner_id' => $request->learner_id,
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
        ]);

        $entry->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Added to waitlist.',
            'data' => $entry,
        ], 201);
    }

    public function acceptWaitlistOffer(Request $request, int $id): JsonResponse
    {
        $entry = WaitlistEntry::findOrFail($id);

        try {
            $booking = $this->bookingService->acceptWaitlistOffer($entry, $request->user()->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => 'Waitlist Error',
                'message' => $e->getMessage(),
            ], 422);
        }

        $booking->load(['resource', 'learner']);

        return response()->json([
            'message' => 'Waitlist offer accepted. Booking confirmed.',
            'data' => $booking,
        ]);
    }

    public function waitlistIndex(Request $request): JsonResponse
    {
        $query = WaitlistEntry::with(['resource', 'learner']);

        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return response()->json($query->orderBy('position', 'asc')->paginate($perPage));
    }
}
