<?php

namespace App\Http\Requests\Employees;

use App\Concerns\SalaryValidationRules;
use App\Enums\SalaryChangeType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEmployeeSalaryRequest extends FormRequest
{
    use SalaryValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('manageSalary', $this->route('employee'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'effective_date' => ['required', 'date'],
            'change_type' => ['required', Rule::enum(SalaryChangeType::class)->except([SalaryChangeType::Initial])],
            'remarks' => ['nullable', 'string', 'max:500'],
            ...$this->salaryBreakdownRules(),
        ];
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
            },
        ];
    }
}
