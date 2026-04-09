<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\AuthorizesRecordAccess;
use App\Http\Requests\BookingRequest;
use App\Http\Resources\BookingResource;
use App\Http\Resources\WaitlistResource;
use App\Models\Booking;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookingController extends Controller
{
    use AuthorizesRecordAccess;
    private BookingService $bookingService;

    public function __construct(BookingService $bookingService)
    {
        $this->bookingService = $bookingService;
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Booking::with(['resource', 'learner', 'bookedByUser']);

        // Scope results based on user role
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('planner')) {
            $query->where('booked_by', $user->id);
        }

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

        return BookingResource::collection($query->orderBy('start_time', 'asc')->paginate($perPage));
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
            'data' => new BookingResource($booking),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $booking = Booking::with(['resource', 'learner', 'bookedByUser'])->findOrFail($id);
        $this->authorizeRecord($request, $booking);

        return response()->json(['data' => new BookingResource($booking)]);
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $booking = Booking::findOrFail($id);
        $this->authorizeRecord($request, $booking);

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
            'data' => new BookingResource($booking),
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:2000',
        ]);

        $booking = Booking::findOrFail($id);
        $this->authorizeMutation($request, $booking);

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
            'data' => new BookingResource($booking),
        ]);
    }

    public function reschedule(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'start_time' => 'required|date|after:now',
            'end_time' => 'required|date|after:start_time',
        ]);

        $booking = Booking::findOrFail($id);
        $this->authorizeMutation($request, $booking);

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
            'data' => new BookingResource($booking),
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
            'data' => new WaitlistResource($entry),
        ], 201);
    }

    public function acceptWaitlistOffer(Request $request, int $id): JsonResponse
    {
        $entry = WaitlistEntry::findOrFail($id);
        $this->authorizeRecord($request, $entry);

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
            'data' => new BookingResource($booking),
        ]);
    }

    public function waitlistIndex(Request $request): AnonymousResourceCollection
    {
        $query = WaitlistEntry::with(['resource', 'learner']);

        // Scope results based on user role
        $user = $request->user();
        if (!$user->hasRole('admin') && !$user->hasRole('planner')) {
            // Restricted roles only see waitlist entries for learners they have bookings for
            $userId = $user->id;
            $query->whereHas('learner', function ($lq) use ($userId) {
                $lq->whereHas('bookings', function ($bq) use ($userId) {
                    $bq->where('booked_by', $userId);
                });
            });
        }

        if ($request->has('resource_id')) {
            $query->where('resource_id', $request->resource_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 25), 100);

        return WaitlistResource::collection($query->orderBy('position', 'asc')->paginate($perPage));
    }
}
