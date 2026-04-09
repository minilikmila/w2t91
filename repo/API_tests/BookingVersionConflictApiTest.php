<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingVersionConflictApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Learner $learner;
    private Resource $resource;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'Administrator', 'slug' => 'admin']);
        foreach (['bookings.create', 'bookings.view', 'bookings.update', 'bookings.cancel'] as $p) {
            $perm = Permission::create(['name' => $p, 'slug' => $p]);
            $role->permissions()->attach($perm);
        }

        $this->user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('SecurePass12!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'bk-ver-token-padded-to-exactly-sixty-four-characters-long-here!';
        ApiToken::create([
            'user_id' => $this->user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 1]);
    }

    public function test_confirm_with_wrong_version_throws(): void
    {
        $startTime = now()->addHours(3)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => 'provisional',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'version' => 1,
            'hold_expires_at' => now()->addMinutes(5),
        ]);

        $service = app(BookingService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version conflict');
        $service->confirmBooking($booking, 999);
    }

    public function test_confirm_with_correct_version_succeeds(): void
    {
        $startTime = now()->addHours(3)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => 'provisional',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'version' => 1,
            'hold_expires_at' => now()->addMinutes(5),
        ]);

        $service = app(BookingService::class);
        $confirmed = $service->confirmBooking($booking, 1);

        $this->assertEquals('confirmed', $confirmed->status);
    }

    public function test_reschedule_with_wrong_version_throws(): void
    {
        $startTime = now()->addDays(3)->startOfHour();
        $newStart = now()->addDays(4)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'version' => 1,
        ]);

        $service = app(BookingService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Version conflict');
        $service->rescheduleBooking($booking, $newStart, $newStart->copy()->addMinutes(30), 5);
    }

    public function test_reschedule_increments_version(): void
    {
        $startTime = now()->addDays(3)->startOfHour();
        $newStart = now()->addDays(4)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'booked_by' => $this->user->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'version' => 1,
        ]);

        $service = app(BookingService::class);
        $rescheduled = $service->rescheduleBooking($booking, $newStart, $newStart->copy()->addMinutes(30));

        $this->assertEquals(2, $rescheduled->version);
    }
}
