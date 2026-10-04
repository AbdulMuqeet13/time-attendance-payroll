<?php

namespace App\Http\Requests\Shifts;

use App\Enums\PermissionEnum;
use App\Models\ShiftAssignment;
use App\Services\ShiftAssignmentService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateShiftAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ShiftAssignment $assignment */
        $assignment = $this->route('shift_assignment');
        $branchId = $this->user()->branch_id;

        return $this->user()->can(PermissionEnum::ShiftsManage->value)
            && ($branchId === null || $assignment->employee->branch_id === $branchId);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return ShiftAssignmentRules::rules();
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

                /** @var ShiftAssignment $assignment */
                $assignment = $this->route('shift_assignment');
                $clashing = app(ShiftAssignmentService::class)
                    ->clashingEmployees([$assignment->employee_id], $this->validated(), $assignment->id);

                if ($clashing !== []) {
                    $validator->errors()->add('shift_id', 'This overlaps another shift the employee already works.');
                }
            },
        ];
    }
}
