<?php

namespace App\Http\Requests\Leaves;

use App\Enums\PermissionEnum;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Services\Leaves\LeaveService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Apply for leave on someone's behalf (HR / managers) or, in self-service, for yourself.
 */
class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::LeavesManage->value)
            || $this->user()->can(PermissionEnum::SelfServiceAccess->value);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'employee_id' => [Rule::requiredIf(! $this->isSelfService()), 'nullable', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'leave_type_id' => ['required', 'integer', Rule::exists('leave_types', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
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

                $employee = $this->employee();

                if (! $employee) {
                    $validator->errors()->add('employee_id', 'You can only apply for employees you manage.');

                    return;
                }

                $type = LeaveType::query()->find((int) $this->input('leave_type_id'));

                if ($type?->requires_attachment && ! $this->hasFile('attachment')) {
                    $validator->errors()->add('attachment', "{$type->name} needs a supporting document.");

                    return;
                }

                if ($problem = app(LeaveService::class)->problemWith($employee, $this->validated())) {
                    $validator->errors()->add('start_date', $problem);
                }
            },
        ];
    }

    /**
     * The employee the leave is for: the chosen one (within the user's branch) or, in self-service, the user's own record.
     */
    public function employee(): ?Employee
    {
        if ($this->isSelfService()) {
            return Employee::query()->where('user_id', $this->user()->id)->first();
        }

        return Employee::query()->visibleTo($this->user())->find((int) $this->input('employee_id'));
    }

    public function isSelfService(): bool
    {
        return ! $this->user()->can(PermissionEnum::LeavesManage->value);
    }
}
