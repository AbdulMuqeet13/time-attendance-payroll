<?php

namespace App\Services\Payroll;

use App\Enums\AdjustmentKind;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class AdjustmentService
{
    /**
     * Give the same adjustment to many employees, as a fixed amount or a percentage of salary.
     *
     * @param  array{kind: string, name: string, period: string, amount_type: string, amount: string|float, percent_of?: string|null, notes?: string|null, branch_id?: int|null, department_id?: int|null, employee_ids?: array<int, int>|null}  $data
     * @return int The number of employees given the adjustment
     */
    public function bulk(array $data, User $user): int
    {
        $period = CarbonImmutable::parse($data['period'])->startOfMonth();
        $batchId = (string) Str::uuid();
        $count = 0;

        Employee::query()
            ->visibleTo($user)
            ->employedBetween($period, $period->endOfMonth())
            ->when($data['employee_ids'] ?? null, fn ($query, array $ids) => $query->whereKey($ids))
            ->when($data['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($data['department_id'] ?? null, fn ($query, $departmentId) => $query->where('department_id', $departmentId))
            ->each(function (Employee $employee) use ($data, $period, $batchId, $user, &$count) {
                $amount = $this->amountFor($employee, $data, $period);

                if (! Money::isPositive($amount)) {
                    return;
                }

                PayrollAdjustment::query()->create([
                    'employee_id' => $employee->id,
                    'period' => $period->toDateString(),
                    'kind' => AdjustmentKind::from($data['kind']),
                    'name' => $data['name'],
                    'amount' => $amount,
                    'notes' => $data['notes'] ?? null,
                    'batch_id' => $batchId,
                    'created_by' => $user->id,
                ]);

                $count++;
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountFor(Employee $employee, array $data, CarbonImmutable $period): string
    {
        if ($data['amount_type'] === 'fixed') {
            return Money::round((string) $data['amount']);
        }

        $salary = $employee->salaryEffectiveOn($period->endOfMonth());

        if (! $salary) {
            return '0';
        }

        $base = $data['percent_of'] === 'basic'
            ? (string) ($salary->components()->whereHas('salaryComponent', fn ($query) => $query->where('name', 'Basic Salary'))->value('amount') ?? $salary->gross_salary)
            : (string) $salary->gross_salary;

        return Money::round(Money::div(Money::mul($base, (string) $data['amount']), 100));
    }
}
