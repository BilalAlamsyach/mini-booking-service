<?php

namespace Database\Factories;

use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Schedule>
 */
class ScheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'vehicle_code' => strtoupper(fake()->bothify('B-####-??')),
            'departure_date' => now()->addDay()->toDateString(),
            'departure_time' => '08:00:00',
            'arrival_time' => '14:00:00',
            'price' => 150000,
        ];
    }

    /**
     * Buat sekaligus sejumlah kursi untuk jadwal ini.
     */
    public function withSeats(int $count = 10): static
    {
        return $this->afterCreating(function ($schedule) use ($count) {
            $rows = [];

            for ($i = 1; $i <= $count; $i++) {
                $rows[] = [
                    'schedule_id' => $schedule->id,
                    'seat_number' => 'A'.$i,
                    'seat_class' => 'economy',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $schedule->seats()->insert($rows);
        });
    }
}
