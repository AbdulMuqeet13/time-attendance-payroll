<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Models\AttendanceDay;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceDay>
 */
class AttendanceDayFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'date' => '2026-10-05',
            'day_type' => DayType::Working,
            'status' => AttendanceStatus::Present,
            'scheduled_minutes' => 480,
            'worked_minutes' => 480,
        ];
    }

    public function status(AttendanceStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
