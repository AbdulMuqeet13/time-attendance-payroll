<?php

namespace App\Http\Requests\Employees;

use App\Concerns\SalaryValidationRules;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEmployeeRequest extends FormRequest
{
    use EmployeeRules, SalaryValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Employee::class);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [...$this->employeeRules(), ...$this->salaryBreakdownRules()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return $this->salaryBreakdownAttributes();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $validator->errors()->has('components') && ! $this->earningsTotalIsPositive()) {
                    $validator->errors()->add('components', 'Enter an amount for at least one earning component.');
                }

                if ($this->user()->branch_id && (int) $this->input('branch_id') !== $this->user()->branch_id) {
                    $validator->errors()->add('branch_id', 'You can only add employees to your own branch.');
                }
            },
        ];
    }
}
