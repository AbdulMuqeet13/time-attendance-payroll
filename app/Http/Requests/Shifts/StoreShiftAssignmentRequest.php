<?php

namespace App\Http\Requests\Shifts;

use App\Enums\PermissionEnum;
use App\Models\Employee;
use App\Services\ShiftAssignmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreShiftAssignmentRequest extends FormRequest
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
            'employee_ids' => ['required', 'array', 'min:1', 'max:500'],
            'employee_ids.*' => ['integer', 'distinct', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            ...ShiftAssignmentRules::rules(),
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $employeeIds = array_map('intval', $this->input('employee_ids'));
                $visible = Employee::query()->visibleTo($this->user())->whereKey($employeeIds)->count();

                if ($visible !== count($employeeIds)) {
                    $validator->errors()->add('employee_ids', 'You can only assign shifts to employees of your branch.');

                    return;
                }

                $clashing = app(ShiftAssignmentService::class)->clashingEmployees($employeeIds, $this->validated());

                if ($clashing !== []) {
                    $validator->errors()->add('employee_ids', 'These employees already have a shift at overlapping times: '.implode(', ', $clashing).'.');
                }
            },
        ];
    }
}
