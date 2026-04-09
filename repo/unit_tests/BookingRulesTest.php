<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\WaitlistEntry;
use Tests\TestCase;

class BookingRulesTest extends TestCase
{
    // --- Booking Status Tests ---

    public function test_provisional_booking_is_provisional(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_PROVISIONAL]);
        $this->assertTrue($booking->isProvisional());
    }

    public function test_confirmed_booking_is_not_provisional(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_CONFIRMED]);
        $this->assertFalse($booking->isProvisional());
    }

    public function test_provisional_booking_is_active(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_PROVISIONAL]);
        $this->assertTrue($booking->isActive());
    }

    public function test_confirmed_booking_is_active(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_CONFIRMED]);
        $this->assertTrue($booking->isActive());
    }

    public function test_cancelled_booking_is_not_active(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_CANCELLED]);
        $this->assertFalse($booking->isActive());
    }

    public function test_late_cancel_booking_is_not_active(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_LATE_CANCEL]);
        $this->assertFalse($booking->isActive());
    }

    public function test_completed_booking_is_not_active(): void
    {
        $booking = new Booking(['status' => Booking::STATUS_COMPLETED]);
        $this->assertFalse($booking->isActive());
    }

    // --- Hold Expiry Tests ---

    public function test_hold_not_expired_when_future(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_PROVISIONAL,
            'hold_expires_at' => now()->addMinutes(5),
        ]);
        $this->assertFalse($booking->isHoldExpired());
    }

    public function test_hold_expired_when_past(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_PROVISIONAL,
            'hold_expires_at' => now()->subMinutes(1),
        ]);
        $this->assertTrue($booking->isHoldExpired());
    }

    public function test_hold_not_expired_when_confirmed(): void
    {
        $booking = new Booking([
            'status' => Booking::STATUS_CONFIRMED,
            'hold_expires_at' => now()->subMinutes(1),
        ]);
        $this->assertFalse($booking->isHoldExpired());
    }

    // --- Status Constants ---

    public function test_status_constants_defined(): void
    {
        $this->assertEquals('provisional', Booking::STATUS_PROVISIONAL);
        $this->assertEquals('confirmed', Booking::STATUS_CONFIRMED);
        $this->assertEquals('cancelled', Booking::STATUS_CANCELLED);
        $this->assertEquals('late_cancel', Booking::STATUS_LATE_CANCEL);
        $this->assertEquals('completed', Booking::STATUS_COMPLETED);
        $this->assertEquals('no_show', Booking::STATUS_NO_SHOW);
    }

    // --- Waitlist Status Tests ---

    public function test_waitlist_offer_not_expired_when_future(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offer_expires_at' => now()->addMinutes(10),
        ]);
        $this->assertFalse($entry->isOfferExpired());
    }

    public function test_waitlist_offer_expired_when_past(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offer_expires_at' => now()->subMinutes(1),
        ]);
        $this->assertTrue($entry->isOfferExpired());
    }

    public function test_waitlist_offer_not_expired_when_waiting(): void
    {
        $entry = new WaitlistEntry([
            'status' => WaitlistEntry::STATUS_WAITING,
            'offer_expires_at' => now()->subMinutes(1),
        ]);
        $this->assertFalse($entry->isOfferExpired());
    }

    public function test_waitlist_status_constants(): void
    {
        $this->assertEquals('waiting', WaitlistEntry::STATUS_WAITING);
        $this->assertEquals('offered', WaitlistEntry::STATUS_OFFERED);
        $this->assertEquals('accepted', WaitlistEntry::STATUS_ACCEPTED);
        $this->assertEquals('expired', WaitlistEntry::STATUS_EXPIRED);
        $this->assertEquals('cancelled', WaitlistEntry::STATUS_CANCELLED);
    }
}
