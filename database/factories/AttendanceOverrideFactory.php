<?php

namespace Database\Factories;

use App\Models\AttendanceOverride;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceOverride>
 */
class AttendanceOverrideFactory extends Factory
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
            'reason' => 'Approved by manager',
        ];
    }
}
