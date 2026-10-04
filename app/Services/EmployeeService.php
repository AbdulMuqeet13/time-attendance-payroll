<?php

namespace App\Services;

use App\Enums\SalaryChangeType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private SalaryService $salaryService) {}

    /**
     * Create an employee together with their initial salary record, effective from the joining date.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?User $user): Employee
    {
        return DB::transaction(function () use ($data, $user) {
            $employee = Employee::create([
                ...Arr::except($data, ['components']),
                'employee_code' => $data['employee_code'] ?? $this->nextEmployeeCode(),
            ]);

            $this->salaryService->record($employee, [
                'effective_date' => $employee->joining_date->toDateString(),
                'change_type' => SalaryChangeType::Initial->value,
                'components' => $data['components'],
            ], $user);

            return $employee;
        });
    }

    /**
     * The next free code in the EMP-0001 sequence.
     */
    public function nextEmployeeCode(): string
    {
        $highest = Employee::withTrashed()
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('employee_code')
            ->map(fn (string $code) => (int) substr($code, 4))
            ->max() ?? 0;

        return sprintf('EMP-%04d', $highest + 1);
    }
}
