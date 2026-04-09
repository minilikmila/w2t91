<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\WaitlistEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    private const HOLD_DURATION_MINUTES = 5;
    private const WAITLIST_OFFER_MINUTES = 10;
    private const MIN_LEAD_TIME_HOURS = 2;
    private const RESCHEDULE_WINDOW_HOURS = 24;
    private const SLOT_INCREMENT_MINUTES = 15;

    /**
     * Create a provisional hold for a booking.
     */
    public function createProvisionalHold(array $data): Booking
    {
        $resourceId = $data['resource_id'];
        $learnerId = $data['learner_id'];
        $startTime = Carbon::parse($data['start_time']);
        $endTime = Carbon::parse($data['end_time']);
        $idempotencyKey = $data['idempotency_key'] ?? Str::uuid()->toString();
        $bookedBy = $data['booked_by'] ?? null;

        // Check idempotency — return existing booking if key matches
        $existing = Booking::where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }

        // Validate slot increment
        $this->validateSlotIncrement($startTime, $endTime);

        // Validate lead time
        $this->validateLeadTime($startTime);

        // Check for conflicts
        $this->checkConflicts($resourceId, $startTime, $endTime);

        // Expire any stale provisional holds
        $this->expireStaleHolds();

        return DB::transaction(function () use ($resourceId, $learnerId, $startTime, $endTime, $idempotencyKey, $bookedBy) {
            return Booking::create([
                'resource_id' => $resourceId,
                'learner_id' => $learnerId,
                'booked_by' => $bookedBy,
                'idempotency_key' => $idempotencyKey,
                'status' => Booking::STATUS_PROVISIONAL,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'version' => 1,
                'hold_expires_at' => now()->addMinutes(self::HOLD_DURATION_MINUTES),
            ]);
        });
    }

    /**
     * Confirm a provisional hold into a final booking.
     */
    public function confirmBooking(Booking $booking): Booking
    {
        if (!$booking->isProvisional()) {
            throw new \InvalidArgumentException('Only provisional bookings can be confirmed.');
        }

        if ($booking->isHoldExpired()) {
            $booking->update(['status' => Booking::STATUS_CANCELLED, 'cancelled_at' => now(), 'cancellation_type' => 'hold_expired']);
            throw new \InvalidArgumentException('Provisional hold has expired.');
        }

        // Re-check conflicts to prevent race conditions
        $this->checkConflicts($booking->resource_id, $booking->start_time, $booking->end_time, $booking->id);

        $booking->update([
            'status' => Booking::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'hold_expires_at' => null,
        ]);

        return $booking->fresh();
    }

    /**
     * Cancel a booking. Creates late_cancel if within 24 hours of start.
     */
    public function cancelBooking(Booking $booking, ?string $notes = null): Booking
    {
        if (!$booking->isActive()) {
            throw new \InvalidArgumentException('This booking cannot be cancelled.');
        }

        $isLateCancel = $booking->start_time->diffInHours(now(), false) > -(self::RESCHEDULE_WINDOW_HOURS);
        $status = $isLateCancel ? Booking::STATUS_LATE_CANCEL : Booking::STATUS_CANCELLED;
        $cancellationType = $isLateCancel ? 'late_cancel' : 'standard';

        $booking->update([
            'status' => $status,
            'cancelled_at' => now(),
            'cancellation_type' => $cancellationType,
            'notes' => $notes,
        ]);

        // Trigger waitlist backfill
        $this->offerToWaitlist($booking->resource_id, $booking->start_time, $booking->end_time);

        return $booking->fresh();
    }

    /**
     * Reschedule a booking to a new time.
     */
    public function rescheduleBooking(Booking $booking, Carbon $newStartTime, Carbon $newEndTime): Booking
    {
        if ($booking->status !== Booking::STATUS_CONFIRMED) {
            throw new \InvalidArgumentException('Only confirmed bookings can be rescheduled.');
        }

        // Check 24-hour reschedule window
        $hoursUntilStart = now()->diffInHours($booking->start_time, false);
        if ($hoursUntilStart < self::RESCHEDULE_WINDOW_HOURS) {
            throw new \InvalidArgumentException(
                'Reschedules must be made at least ' . self::RESCHEDULE_WINDOW_HOURS . ' hours before the original start time.'
            );
        }

        $this->validateSlotIncrement($newStartTime, $newEndTime);
        $this->validateLeadTime($newStartTime);
        $this->checkConflicts($booking->resource_id, $newStartTime, $newEndTime, $booking->id);

        $booking->update([
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
            'version' => $booking->version + 1,
        ]);

        return $booking->fresh();
    }

    /**
     * Add a learner to the waitlist for a resource slot.
     */
    public function addToWaitlist(array $data): WaitlistEntry
    {
        $maxPosition = WaitlistEntry::where('resource_id', $data['resource_id'])
            ->where('status', WaitlistEntry::STATUS_WAITING)
            ->max('position') ?? 0;

        return WaitlistEntry::create([
            'resource_id' => $data['resource_id'],
            'learner_id' => $data['learner_id'],
            'desired_start_time' => $data['start_time'],
            'desired_end_time' => $data['end_time'],
            'status' => WaitlistEntry::STATUS_WAITING,
            'position' => $maxPosition + 1,
        ]);
    }

    /**
     * Accept a waitlist offer and create a confirmed booking.
     */
    public function acceptWaitlistOffer(WaitlistEntry $entry, int $bookedBy): Booking
    {
        if ($entry->status !== WaitlistEntry::STATUS_OFFERED) {
            throw new \InvalidArgumentException('This waitlist entry does not have an active offer.');
        }

        if ($entry->isOfferExpired()) {
            $entry->update(['status' => WaitlistEntry::STATUS_EXPIRED]);
            throw new \InvalidArgumentException('The waitlist offer has expired.');
        }

        $this->checkConflicts($entry->resource_id, $entry->desired_start_time, $entry->desired_end_time);

        $booking = DB::transaction(function () use ($entry, $bookedBy) {
            $entry->update([
                'status' => WaitlistEntry::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            return Booking::create([
                'resource_id' => $entry->resource_id,
                'learner_id' => $entry->learner_id,
                'booked_by' => $bookedBy,
                'idempotency_key' => Str::uuid()->toString(),
                'status' => Booking::STATUS_CONFIRMED,
                'start_time' => $entry->desired_start_time,
                'end_time' => $entry->desired_end_time,
                'version' => 1,
                'confirmed_at' => now(),
            ]);
        });

        return $booking;
    }

    /**
     * Offer the next waitlist entry for a freed slot.
     */
    private function offerToWaitlist(int $resourceId, Carbon $startTime, Carbon $endTime): void
    {
        $entry = WaitlistEntry::where('resource_id', $resourceId)
            ->where('status', WaitlistEntry::STATUS_WAITING)
            ->where('desired_start_time', $startTime)
            ->where('desired_end_time', $endTime)
            ->orderBy('position', 'asc')
            ->first();

        if ($entry) {
            $entry->update([
                'status' => WaitlistEntry::STATUS_OFFERED,
                'offered_at' => now(),
                'offer_expires_at' => now()->addMinutes(self::WAITLIST_OFFER_MINUTES),
            ]);
        }
    }

    /**
     * Expire stale provisional holds and waitlist offers.
     */
    public function expireStaleHolds(): void
    {
        // Expire provisional bookings past hold time
        Booking::where('status', Booking::STATUS_PROVISIONAL)
            ->where('hold_expires_at', '<', now())
            ->update([
                'status' => Booking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_type' => 'hold_expired',
            ]);

        // Expire waitlist offers past offer time
        WaitlistEntry::where('status', WaitlistEntry::STATUS_OFFERED)
            ->where('offer_expires_at', '<', now())
            ->update([
                'status' => WaitlistEntry::STATUS_EXPIRED,
            ]);
    }

    /**
     * Validate start/end times are in 15-minute increments.
     */
    private function validateSlotIncrement(Carbon $startTime, Carbon $endTime): void
    {
        if ($startTime->minute % self::SLOT_INCREMENT_MINUTES !== 0) {
            throw new \InvalidArgumentException('Start time must be in ' . self::SLOT_INCREMENT_MINUTES . '-minute increments.');
        }

        if ($endTime->minute % self::SLOT_INCREMENT_MINUTES !== 0) {
            throw new \InvalidArgumentException('End time must be in ' . self::SLOT_INCREMENT_MINUTES . '-minute increments.');
        }

        if ($endTime->lte($startTime)) {
            throw new \InvalidArgumentException('End time must be after start time.');
        }
    }

    /**
     * Validate minimum lead time before booking.
     */
    private function validateLeadTime(Carbon $startTime): void
    {
        $hoursUntilStart = now()->diffInHours($startTime, false);

        if ($hoursUntilStart < self::MIN_LEAD_TIME_HOURS) {
            throw new \InvalidArgumentException(
                'Bookings must be made at least ' . self::MIN_LEAD_TIME_HOURS . ' hours in advance.'
            );
        }
    }

    /**
     * Check for booking conflicts on a resource.
     */
    private function checkConflicts(int $resourceId, Carbon $startTime, Carbon $endTime, ?int $excludeBookingId = null): void
    {
        $query = Booking::where('resource_id', $resourceId)
            ->whereIn('status', [Booking::STATUS_PROVISIONAL, Booking::STATUS_CONFIRMED])
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeBookingId) {
            $query->where('id', '!=', $excludeBookingId);
        }

        $resource = Resource::findOrFail($resourceId);
        $conflictCount = $query->count();

        if ($conflictCount >= $resource->capacity) {
            throw new \InvalidArgumentException(
                'Resource is fully booked for the requested time slot. ' .
                "Capacity: {$resource->capacity}, Current bookings: {$conflictCount}."
            );
        }
    }
}
