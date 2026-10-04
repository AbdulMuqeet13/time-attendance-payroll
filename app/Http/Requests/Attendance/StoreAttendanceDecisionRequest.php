<?php

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Override a day's status or waive a late (attendance.manage).
 */
class StoreAttendanceDecisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('attendanceDay'));
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)->only(AttendanceStatus::overridable())],
            'waive_late' => ['boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
