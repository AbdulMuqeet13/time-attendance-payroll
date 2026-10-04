<?php

namespace App\Services;

use App\Enums\SalaryComponentType;
use App\Exceptions\Salaries\OnlySalaryRecordException;
use App\Exceptions\Salaries\SalaryRecordInUseException;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\SalaryComponent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalaryService
{
    /**
     * Record a new salary for an employee (initial, increment, decrement or revision).
     *
     * Earning components add up to the gross salary; deduction components (e.g. income tax) add up to the
     * fixed monthly deductions. Components with a zero or blank amount are not stored.
     *
     * @param  array{
     *     effective_date: string,
     *     change_type: string,
     *     components: array<int, array{salary_component_id: int|string, amount: string|int|float|null}>,
     *     remarks?: string|null,
     * }  $data
     */
    public function record(Employee $employee, array $data, ?User $user): EmployeeSalary
    {
        return DB::transaction(function () use ($employee, $data, $user) {
            $components = collect($data['components'])
                ->filter(fn (array $component) => is_numeric($component['amount'] ?? null)
                    && bccomp((string) $component['amount'], '0', 2) === 1)
                ->values();

            $types = SalaryComponent::withTrashed()
                ->whereIn('id', $components->pluck('salary_component_id'))
                ->pluck('type', 'id');

            $sum = fn (SalaryComponentType $type): string => $components
                ->filter(fn (array $component) => $types[$component['salary_component_id']] === $type)
                ->reduce(fn (string $total, array $component) => bcadd($total, (string) $component['amount'], 2), '0.00');

            $salary = $employee->salaries()->create([
                'effective_date' => $data['effective_date'],
                'change_type' => $data['change_type'],
                'gross_salary' => $sum(SalaryComponentType::Earning),
                'fixed_deductions' => $sum(SalaryComponentType::Deduction),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $user?->id,
            ]);

            foreach ($components as $component) {
                $salary->components()->create([
                    'salary_component_id' => $component['salary_component_id'],
                    'amount' => $component['amount'],
                ]);
            }

            return $salary->load('components.salaryComponent');
        });
    }

    /**
     * Delete a salary record that has not been used in payroll.
     *
     * @throws OnlySalaryRecordException
     * @throws SalaryRecordInUseException
     */
    public function delete(EmployeeSalary $salary): void
    {
        if ($salary->payslips()->exists()) {
            throw new SalaryRecordInUseException;
        }

        if (EmployeeSalary::query()->where('employee_id', $salary->employee_id)->count() <= 1) {
            throw new OnlySalaryRecordException;
        }

        $salary->delete();
    }
}
