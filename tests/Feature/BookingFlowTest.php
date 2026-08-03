<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\Schedule;
use App\Models\SeatHold;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_can_be_confirmed_with_valid_lock(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->withSeats(2)->create();
        $seat = $schedule->seats()->first();

        $hold = SeatHold::create([
            'seat_id' => $seat->id,
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'lock_token' => '11111111-1111-1111-1111-111111111111',
            'status' => SeatHold::STATUS_LOCKED,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', [
                'lock_token' => $hold->lock_token,
                'passenger_name' => 'Test User',
                'passenger_phone' => '081234567890',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['message' => 'Pemesanan berhasil dikonfirmasi.']);
    }

    public function test_booking_requires_authentication(): void
    {
        $response = $this->postJson('/api/bookings', [
            'lock_token' => '11111111-1111-1111-1111-111111111111',
            'passenger_name' => 'Test User',
            'passenger_phone' => '081234567890',
        ]);

        $response->assertUnauthorized();
    }

    public function test_expired_lock_cannot_be_confirmed(): void
    {
        $user = User::factory()->create();
        $schedule = Schedule::factory()->withSeats(1)->create();
        $seat = $schedule->seats()->first();

        $hold = SeatHold::create([
            'seat_id' => $seat->id,
            'schedule_id' => $schedule->id,
            'user_id' => $user->id,
            'lock_token' => '22222222-2222-2222-2222-222222222222',
            'status' => SeatHold::STATUS_LOCKED,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', [
                'lock_token' => $hold->lock_token,
                'passenger_name' => 'Test User',
                'passenger_phone' => '081234567890',
            ]);

        $response->assertStatus(410);
        $response->assertJsonFragment(['error_code' => 'LOCK_EXPIRED']);
    }

    public function test_booking_fails_when_lock_belongs_to_another_user(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $schedule = Schedule::factory()->withSeats(1)->create();
        $seat = $schedule->seats()->first();

        $hold = SeatHold::create([
            'seat_id' => $seat->id,
            'schedule_id' => $schedule->id,
            'user_id' => $owner->id,
            'lock_token' => '33333333-3333-3333-3333-333333333333',
            'status' => SeatHold::STATUS_LOCKED,
            'expires_at' => now()->addMinutes(10),
        ]);

        $response = $this->actingAs($otherUser, 'sanctum')
            ->postJson('/api/bookings', [
                'lock_token' => $hold->lock_token,
                'passenger_name' => 'Test User',
                'passenger_phone' => '081234567890',
            ]);

        $response->assertStatus(404);
        $response->assertJsonFragment(['error_code' => 'LOCK_NOT_FOUND']);
    }
}
