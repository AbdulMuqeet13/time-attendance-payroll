<?php

namespace App\Http\Requests\Shifts;

use App\Enums\PermissionEnum;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveRosterOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::ShiftsManage->value);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'date' => ['required', 'date'],
            'shift_id' => ['nullable', 'integer', Rule::exists('shifts', 'id')->whereNull('deleted_at')],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('employee_id')) {
                    return;
                }

                if (! Employee::query()->visibleTo($this->user())->whereKey($this->input('employee_id'))->exists()) {
                    $validator->errors()->add('employee_id', 'You can only change the roster of employees in your branch.');
                }
            },
        ];
    }
}
