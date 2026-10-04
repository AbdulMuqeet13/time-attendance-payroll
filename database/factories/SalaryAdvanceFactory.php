<?php

namespace Database\Factories;

use App\Enums\AdvanceStatus;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalaryAdvance>
 */
class SalaryAdvanceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'amount' => '10000.00',
            'issued_on' => '2026-09-15',
            'installment_amount' => '2500.00',
            'start_period' => '2026-10-01',
            'status' => AdvanceStatus::Active,
        ];
    }
}
