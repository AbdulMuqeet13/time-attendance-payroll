<?php

namespace App\Http\Requests\Attendance;

use App\Enums\PermissionEnum;
use App\Models\Employee;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreManualPunchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::AttendanceManage->value);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'punched_at' => ['required', 'date_format:Y-m-d H:i', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function employee(): Employee
    {
        return Employee::query()->visibleTo($this->user())->findOrFail((int) $this->validated('employee_id'));
    }
}
