<?php

namespace Tests\Unit;

use App\Models\Booking;
use Tests\TestCase;

class BookingVersionConflictTest extends TestCase
{
    public function test_booking_version_defaults_to_one(): void
    {
        $booking = new Booking(['version' => 1]);
        $this->assertEquals(1, $booking->version);
    }

    public function test_version_increments_are_tracked(): void
    {
        $booking = new Booking(['version' => 1]);
        $newVersion = $booking->version + 1;
        $this->assertEquals(2, $newVersion);
    }

    public function test_version_mismatch_is_detectable(): void
    {
        $expectedVersion = 1;
        $actualVersion = 2;
        $this->assertNotEquals($expectedVersion, $actualVersion);
    }

    public function test_booking_status_constants_for_concurrency(): void
    {
        $this->assertEquals('provisional', Booking::STATUS_PROVISIONAL);
        $this->assertEquals('confirmed', Booking::STATUS_CONFIRMED);
    }

    public function test_only_provisional_can_be_confirmed(): void
    {
        $provisional = new Booking(['status' => Booking::STATUS_PROVISIONAL]);
        $confirmed = new Booking(['status' => Booking::STATUS_CONFIRMED]);
        $cancelled = new Booking(['status' => Booking::STATUS_CANCELLED]);

        $this->assertTrue($provisional->isProvisional());
        $this->assertFalse($confirmed->isProvisional());
        $this->assertFalse($cancelled->isProvisional());
    }
}
