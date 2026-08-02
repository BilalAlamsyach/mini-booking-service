<?php

namespace Database\Factories;

use App\Models\Operator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Route>
 */
class RouteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'operator_id' => Operator::factory(),
            'origin' => fake()->randomElement(['Jakarta', 'Bandung', 'Semarang', 'Surabaya']),
            'destination' => fake()->randomElement(['Yogyakarta', 'Malang', 'Solo', 'Cirebon']),
            'duration_minutes' => fake()->numberBetween(120, 720),
        ];
    }
}
