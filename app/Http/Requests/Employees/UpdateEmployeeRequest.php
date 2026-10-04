<?php

namespace App\Http\Requests\Employees;

use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Updates the profile only. Salary changes go through a new salary record so history is kept.
 */
class UpdateEmployeeRequest extends FormRequest
{
    use EmployeeRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('employee'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return $this->employeeRules($employee);
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->user()->branch_id && (int) $this->input('branch_id') !== $this->user()->branch_id) {
                    $validator->errors()->add('branch_id', 'You can only keep employees in your own branch.');
                }
            },
        ];
    }
}
