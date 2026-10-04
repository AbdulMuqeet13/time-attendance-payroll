<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\RosterOverride;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RosterOverride>
 */
class RosterOverrideFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-10-05',
            'shift_id' => null,
        ];
    }
}
