<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShiftAssignment>
 */
class ShiftAssignmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'shift_id' => Shift::factory(),
            'days' => null,
            'effective_from' => '2025-01-01',
            'effective_to' => null,
        ];
    }

    /**
     * @param  array<int, int>  $days  0 = Sunday … 6 = Saturday
     */
    public function onDays(array $days): static
    {
        return $this->state(fn () => ['days' => $days]);
    }

    /**
     * Monday to Saturday, the common six-day week.
     */
    public function mondayToSaturday(): static
    {
        return $this->onDays([1, 2, 3, 4, 5, 6]);
    }
}
