<?php

namespace Database\Factories;

use App\Enums\AdjustmentKind;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollAdjustment>
 */
class PayrollAdjustmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'period' => '2026-10-01',
            'kind' => AdjustmentKind::Bonus,
            'name' => 'Performance bonus',
            'amount' => '5000.00',
        ];
    }
}
