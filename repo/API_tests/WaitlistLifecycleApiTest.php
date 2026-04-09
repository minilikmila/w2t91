<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class WaitlistLifecycleApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private User $user;
    private Learner $learner;
    private Learner $learner2;
    private Resource $resource;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        $perms = ['bookings.create', 'bookings.view', 'bookings.update', 'bookings.cancel'];
        foreach ($perms as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $this->user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'wait-token-padded-to-exactly-sixty-four-characters-long-here-ok!';
        ApiToken::create([
            'user_id' => $this->user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create(['first_name' => 'Jane', 'last_name' => 'Doe']);
        $this->learner2 = Learner::create(['first_name' => 'Bob', 'last_name' => 'Smith']);
        $this->resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 1]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_waitlist_entry_gets_offered_after_cancellation(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Book the resource (fills capacity = 1)
        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => Booking::STATUS_CONFIRMED,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'version' => 1,
            'confirmed_at' => now(),
        ]);

        // Add learner2 to waitlist
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/waitlist', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner2->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);

        $response->assertStatus(201);
        $entryId = $response->json('data.id');

        // Cancel the booking — should trigger waitlist offer
        $cancelResponse = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $cancelResponse->assertStatus(200);

        // Verify the waitlist entry was offered
        $entry = WaitlistEntry::find($entryId);
        $this->assertEquals(WaitlistEntry::STATUS_OFFERED, $entry->status);
        $this->assertNotNull($entry->offered_at);
        $this->assertNotNull($entry->offer_expires_at);
    }

    public function test_expired_waitlist_offer_cannot_be_accepted(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create an already-expired waitlist offer
        $entry = WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offered_at' => now()->subMinutes(15),
            'offer_expires_at' => now()->subMinutes(5),
            'position' => 1,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/waitlist/{$entry->id}/accept");

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'Waitlist Error']);
    }

    public function test_accept_valid_waitlist_offer_creates_booking(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create a valid waitlist offer
        $entry = WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offered_at' => now(),
            'offer_expires_at' => now()->addMinutes(10),
            'position' => 1,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/waitlist/{$entry->id}/accept");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.learner_id', $this->learner->id);

        // Verify the entry was marked as accepted
        $entry->refresh();
        $this->assertEquals(WaitlistEntry::STATUS_ACCEPTED, $entry->status);
    }

    public function test_waitlist_position_ordering(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Add first entry
        $response1 = $this->withHeaders($this->auth())
            ->postJson('/api/waitlist', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);
        $response1->assertStatus(201);

        // Add second entry
        $response2 = $this->withHeaders($this->auth())
            ->postJson('/api/waitlist', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner2->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);
        $response2->assertStatus(201);

        // Second entry should have higher position
        $this->assertGreaterThan(
            $response1->json('data.position'),
            $response2->json('data.position')
        );
    }

    public function test_stale_holds_are_expired(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create a stale provisional hold
        Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => Booking::STATUS_PROVISIONAL,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'version' => 1,
            'hold_expires_at' => now()->subMinutes(1),
        ]);

        // Trigger expiry via the booking service
        $service = app(BookingService::class);
        $service->expireStaleHolds();

        // Verify the hold was expired
        $booking = Booking::where('resource_id', $this->resource->id)->first();
        $this->assertEquals(Booking::STATUS_CANCELLED, $booking->status);
        $this->assertEquals('hold_expired', $booking->cancellation_type);
    }

    public function test_expired_waitlist_offers_are_cleaned_up(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create an expired offer
        WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_OFFERED,
            'offered_at' => now()->subMinutes(15),
            'offer_expires_at' => now()->subMinutes(1),
            'position' => 1,
        ]);

        // Trigger expiry
        $service = app(BookingService::class);
        $service->expireStaleHolds();

        // Verify the offer was expired
        $entry = WaitlistEntry::where('resource_id', $this->resource->id)->first();
        $this->assertEquals(WaitlistEntry::STATUS_EXPIRED, $entry->status);
    }

    public function test_waitlist_cannot_accept_waiting_entry(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create a waiting entry (not yet offered)
        $entry = WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_WAITING,
            'position' => 1,
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/waitlist/{$entry->id}/accept");

        $response->assertStatus(422)
            ->assertJsonFragment(['error' => 'Waitlist Error']);
    }

    public function test_waitlist_list_endpoint_returns_entries(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_WAITING,
            'position' => 1,
        ]);

        $response = $this->withHeaders($this->auth())
            ->getJson('/api/waitlist');

        $response->assertStatus(200);
    }

    public function test_next_person_backfill_after_expired_offer(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Book and fill capacity
        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => Booking::STATUS_CONFIRMED,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'version' => 1,
            'confirmed_at' => now(),
        ]);

        // Add two learners to waitlist
        $entry1 = WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_WAITING,
            'position' => 1,
        ]);

        $entry2 = WaitlistEntry::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner2->id,
            'desired_start_time' => $startTime,
            'desired_end_time' => $endTime,
            'status' => WaitlistEntry::STATUS_WAITING,
            'position' => 2,
        ]);

        // Cancel booking — first in line gets offered
        $service = app(BookingService::class);
        $service->cancelBooking($booking);

        $entry1->refresh();
        $this->assertEquals(WaitlistEntry::STATUS_OFFERED, $entry1->status);

        // Entry 2 should still be waiting
        $entry2->refresh();
        $this->assertEquals(WaitlistEntry::STATUS_WAITING, $entry2->status);
    }

    public function test_full_hold_confirm_cancel_reschedule_sequence(): void
    {
        $startTime = now()->addDays(3)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Step 1: Create provisional hold via API
        $holdResponse = $this->withHeaders($this->auth())
            ->postJson('/api/bookings', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);

        $holdResponse->assertStatus(201);
        $bookingId = $holdResponse->json('data.id');
        $this->assertEquals('provisional', $holdResponse->json('data.status'));

        // Step 2: Confirm the hold
        $confirmResponse = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$bookingId}/confirm");

        $confirmResponse->assertStatus(200);
        $this->assertEquals('confirmed', $confirmResponse->json('data.status'));

        // Step 3: Cancel the confirmed booking
        $cancelResponse = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$bookingId}/cancel");

        $cancelResponse->assertStatus(200);

        // Verify booking is cancelled
        $booking = Booking::find($bookingId);
        $this->assertNotNull($booking->cancelled_at);
    }

    public function test_hold_expire_frees_slot_for_new_booking(): void
    {
        $startTime = now()->addDays(2)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // Create a hold that has already expired
        Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => Booking::STATUS_PROVISIONAL,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'version' => 1,
            'hold_expires_at' => now()->subMinutes(1),
        ]);

        // Expire stale holds
        $service = app(BookingService::class);
        $service->expireStaleHolds();

        // Verify the expired hold is cancelled
        $expired = Booking::where('resource_id', $this->resource->id)
            ->where('learner_id', $this->learner->id)
            ->first();
        $this->assertEquals(Booking::STATUS_CANCELLED, $expired->status);

        // Now a new booking should succeed for the same slot
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/bookings', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner2->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);

        $response->assertStatus(201);
    }
}
