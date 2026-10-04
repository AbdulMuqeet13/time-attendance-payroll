<?php

namespace App\Http\Requests\Employees;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Profile fields shared by the create and update employee requests.
 */
trait EmployeeRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function employeeRules(?Employee $employee = null): array
    {
        return [
            'employee_code' => [$employee ? 'required' : 'nullable', 'string', 'max:30', Rule::unique('employees', 'employee_code')->ignore($employee)],
            'name' => ['required', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20', Rule::unique('employees', 'cnic')->ignore($employee)],
            'gender' => ['required', Rule::enum(Gender::class)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'designation_id' => ['nullable', 'integer', Rule::exists('designations', 'id')->whereNull('deleted_at')],
            'reports_to_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at'), Rule::notIn(array_filter([$employee?->id]))],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'status' => ['required', Rule::enum(EmploymentStatus::class)],
            'joining_date' => ['required', 'date'],
            'confirmation_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:joining_date', Rule::requiredIf(fn () => in_array($this->input('status'), [EmploymentStatus::Resigned->value, EmploymentStatus::Terminated->value], true))],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'bank_name' => ['nullable', 'required_if:payment_method,bank', 'string', 'max:255'],
            'account_title' => ['nullable', 'string', 'max:255'],
            'account_number' => ['nullable', 'required_if:payment_method,bank', 'string', 'max:50'],
            'device_pin' => ['nullable', 'string', 'max:14', 'alpha_num:ascii', Rule::unique('employees', 'device_pin')->ignore($employee)],
        ];
    }

    /**
     * Device PINs are stored upper-case and trimmed, the way ZKTeco devices report them.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('device_pin')) {
            $this->merge(['device_pin' => strtoupper(trim((string) $this->input('device_pin')))]);
        }
    }
}
