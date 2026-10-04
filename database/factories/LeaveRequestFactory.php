<?php

namespace Database\Factories;

use App\Enums\LeaveStatus;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
            'is_half_day' => false,
            'days' => 1,
            'reason' => 'Family matter',
            'status' => LeaveStatus::Pending,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => LeaveStatus::Approved, 'decided_at' => now()]);
    }

    public function between(string $start, string $end, float $days): static
    {
        return $this->state(fn () => ['start_date' => $start, 'end_date' => $end, 'days' => $days]);
    }
}
