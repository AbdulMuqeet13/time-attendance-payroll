<?php

namespace Database\Factories;

use App\Enums\SalaryChangeType;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'effective_date' => '2025-01-01',
            'change_type' => SalaryChangeType::Initial,
            'gross_salary' => '60000.00',
            'fixed_deductions' => '0.00',
        ];
    }
}
