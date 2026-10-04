<?php

namespace Database\Factories;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Enums\SalaryChangeType;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'father_name' => fake()->name('male'),
            'cnic' => fake()->unique()->numerify('#####-#######-#'),
            'gender' => fake()->randomElement(Gender::cases()),
            'date_of_birth' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'phone' => fake()->numerify('03#########'),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'branch_id' => Branch::factory(),
            'employment_type' => EmploymentType::Permanent,
            'status' => EmploymentStatus::Active,
            'joining_date' => '2025-01-01',
            'payment_method' => PaymentMethod::Bank,
            'bank_name' => 'Meezan Bank',
            'account_title' => fake()->name(),
            'account_number' => fake()->numerify('##############'),
            'device_pin' => (string) fake()->unique()->numberBetween(1, 99999),
        ];
    }

    /**
     * Give the employee an initial salary record with a single Basic Salary component.
     */
    public function withSalary(string $gross = '60000.00', ?string $effectiveDate = null): static
    {
        return $this->afterCreating(function (Employee $employee) use ($gross, $effectiveDate) {
            $salary = EmployeeSalary::factory()->for($employee)->create([
                'effective_date' => $effectiveDate ?? $employee->joining_date->toDateString(),
                'change_type' => SalaryChangeType::Initial,
                'gross_salary' => $gross,
            ]);

            $salary->components()->create([
                'salary_component_id' => SalaryComponent::query()->where('name', 'Basic Salary')->value('id')
                    ?? SalaryComponent::factory()->create(['name' => 'Basic Salary'])->id,
                'amount' => $gross,
            ]);
        });
    }

    public function exited(string $exitDate, EmploymentStatus $status = EmploymentStatus::Resigned): static
    {
        return $this->state(fn () => ['status' => $status, 'exit_date' => $exitDate]);
    }
}
