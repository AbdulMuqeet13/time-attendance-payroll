<?php

namespace Database\Factories;

use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveType>
 */
class LeaveTypeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => ucwords(fake()->unique()->word()).' Leave',
            'code' => strtoupper(fake()->unique()->lexify('??')),
            'is_paid' => true,
            'yearly_quota' => 10,
            'carry_forward_max' => 0,
            'allow_half_day' => true,
            'requires_attachment' => false,
            'is_active' => true,
        ];
    }

    public function unpaid(): static
    {
        return $this->state(fn () => ['is_paid' => false, 'yearly_quota' => 0]);
    }
}
