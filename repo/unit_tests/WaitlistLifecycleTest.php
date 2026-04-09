<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\WaitlistEntry;
use Tests\TestCase;

class WaitlistLifecycleTest extends TestCase
{
    // --- Waitlist Offer Expiry ---

    public function test_waitlist_offer_expires_after_deadline(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offer_expires_at' => now()->subSeconds(1),
        ]);

        $this->assertTrue($entry->isOfferExpired());
    }

    public function test_waitlist_offer_not_expired_before_deadline(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offer_expires_at' => now()->addMinutes(5),
        ]);

        $this->assertFalse($entry->isOfferExpired());
    }

    public function test_waitlist_waiting_entry_is_not_expired_even_with_past_time(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_WAITING,
            'offer_expires_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($entry->isOfferExpired());
    }

    public function test_waitlist_accepted_entry_is_not_expired(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_ACCEPTED,
            'offer_expires_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($entry->isOfferExpired());
    }

    public function test_waitlist_cancelled_entry_is_not_expired(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_CANCELLED,
            'offer_expires_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($entry->isOfferExpired());
    }

    // --- Waitlist Status Transitions ---

    public function test_waitlist_status_waiting_to_offered(): void
    {
        $entry = new WaitlistEntry(['status' => WaitlistEntry::STATUS_WAITING]);

        $this->assertEquals('waiting', $entry->status);
        $entry->status = WaitlistEntry::STATUS_OFFERED;
        $this->assertEquals('offered', $entry->status);
    }

    public function test_waitlist_status_offered_to_accepted(): void
    {
        $entry = new WaitlistEntry(['status' => WaitlistEntry::STATUS_OFFERED]);

        $entry->status = WaitlistEntry::STATUS_ACCEPTED;
        $this->assertEquals('accepted', $entry->status);
    }

    public function test_waitlist_status_offered_to_expired(): void
    {
        $entry = new WaitlistEntry(['status' => WaitlistEntry::STATUS_OFFERED]);

        $entry->status = WaitlistEntry::STATUS_EXPIRED;
        $this->assertEquals('expired', $entry->status);
    }

    // --- Booking Hold Expiry Lifecycle ---

    public function test_provisional_hold_expires_and_becomes_inactive(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_PROVISIONAL,
            'hold_expires_at' => now()->subMinutes(1),
        ]);

        $this->assertTrue($booking->isHoldExpired());
        $this->assertTrue($booking->isActive()); // Still technically active until cleaned up
        $this->assertTrue($booking->isProvisional());
    }

    public function test_confirmed_booking_cannot_have_expired_hold(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_CONFIRMED,
            'hold_expires_at' => now()->subMinutes(10),
        ]);

        $this->assertFalse($booking->isHoldExpired());
        $this->assertTrue($booking->isActive());
    }

    public function test_cancelled_booking_after_hold_expiry_is_inactive(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancellation_type' => 'hold_expired',
        ]);

        $this->assertFalse($booking->isActive());
        $this->assertFalse($booking->isProvisional());
    }

    // --- Waitlist Position Ordering ---

    public function test_waitlist_position_values(): void
    {
        $entry1 = new WaitlistEntry(['position' => 1, 'status' => WaitlistEntry::STATUS_WAITING]);
        $entry2 = new WaitlistEntry(['position' => 2, 'status' => WaitlistEntry::STATUS_WAITING]);
        $entry3 = new WaitlistEntry(['position' => 3, 'status' => WaitlistEntry::STATUS_WAITING]);

        $this->assertLessThan($entry2->position, $entry1->position);
        $this->assertLessThan($entry3->position, $entry2->position);
    }

    // --- Waitlist Offer with Null Expiry ---

    public function test_waitlist_offer_without_expiry_is_not_expired(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offer_expires_at' => null,
        ]);

        $this->assertFalse($entry->isOfferExpired());
    }
}
