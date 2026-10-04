<?php

namespace Database\Factories;

use App\Models\Holiday;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => fake()->dateTimeBetween('2026-01-01', '2026-12-31')->format('Y-m-d'),
            'name' => fake()->randomElement(['Kashmir Day', 'Pakistan Day', 'Labour Day', 'Independence Day', 'Eid ul Fitr']),
            'branch_id' => null,
        ];
    }
}
