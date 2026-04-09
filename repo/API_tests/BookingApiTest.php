<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\Booking;
use App\Models\Learner;
use App\Models\Permission;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;
    private Learner $learner;
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

        $user = User::create([
            'username' => 'admin', 'name' => 'Admin', 'email' => 'admin@test.com',
            'password' => Hash::make('Pass1234!@'), 'role_id' => $role->id, 'is_active' => true,
        ]);

        $this->token = 'book-token-padded-to-exactly-sixty-four-characters-long-here-ok!';
        ApiToken::create([
            'user_id' => $user->id,
            'token' => hash('sha256', $this->token),
            'expires_at' => now()->addHours(24),
        ]);

        $this->learner = Learner::create(['first_name' => 'John', 'last_name' => 'Doe']);
        $this->resource = Resource::create(['name' => 'Room A', 'type' => 'room', 'capacity' => 1]);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    public function test_create_provisional_booking(): void
    {
        $startTime = now()->addHours(3)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/bookings', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'provisional');
    }

    public function test_confirm_booking(): void
    {
        $startTime = now()->addHours(3)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'status' => 'provisional',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
            'hold_expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$booking->id}/confirm");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_booking_conflict_detected(): void
    {
        $startTime = now()->addHours(3)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        // First booking
        Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);

        // Second booking for same slot (capacity = 1)
        $response = $this->withHeaders($this->auth())
            ->postJson('/api/bookings', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $endTime->toIso8601String(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('error', 'Booking Error');
    }

    public function test_cancel_booking(): void
    {
        $startTime = now()->addDays(3)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_late_cancel_within_24_hours(): void
    {
        $startTime = now()->addHours(12)->startOfHour();

        $booking = Booking::create([
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'status' => 'confirmed',
            'start_time' => $startTime,
            'end_time' => $startTime->copy()->addMinutes(30),
        ]);

        $response = $this->withHeaders($this->auth())
            ->postJson("/api/bookings/{$booking->id}/cancel");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'late_cancel');
    }

    public function test_idempotent_booking(): void
    {
        $startTime = now()->addHours(3)->startOfHour();
        $endTime = $startTime->copy()->addMinutes(30);

        $payload = [
            'resource_id' => $this->resource->id,
            'learner_id' => $this->learner->id,
            'start_time' => $startTime->toIso8601String(),
            'end_time' => $endTime->toIso8601String(),
            'idempotency_key' => 'unique-key-123',
        ];

        $response1 = $this->withHeaders($this->auth())->postJson('/api/bookings', $payload);
        $response2 = $this->withHeaders($this->auth())->postJson('/api/bookings', $payload);

        $response1->assertStatus(201);
        $response2->assertStatus(201);

        $this->assertEquals(
            $response1->json('data.id'),
            $response2->json('data.id')
        );
    }

    public function test_list_bookings(): void
    {
        $response = $this->withHeaders($this->auth())
            ->getJson('/api/bookings');

        $response->assertStatus(200);
    }

    public function test_add_to_waitlist(): void
    {
        $startTime = now()->addHours(3)->startOfHour();

        $response = $this->withHeaders($this->auth())
            ->postJson('/api/waitlist', [
                'resource_id' => $this->resource->id,
                'learner_id' => $this->learner->id,
                'start_time' => $startTime->toIso8601String(),
                'end_time' => $startTime->copy()->addMinutes(30)->toIso8601String(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'waiting');
    }
}
