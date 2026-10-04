<?php

namespace App\Http\Requests\Shifts;

use App\Enums\PermissionEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveShiftRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'different:start_time'],
            'break_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'late_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'early_window_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'checkout_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:720'],
            'half_day_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'min_overtime_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'color' => ['required', 'string', 'in:sky,emerald,amber,violet,rose,slate'],
            'is_active' => ['boolean'],
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

                $toMinutes = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);
                $start = $toMinutes($this->input('start_time'));
                $end = $toMinutes($this->input('end_time'));
                $span = $end > $start ? $end - $start : $end + 1440 - $start;

                if ((int) $this->input('break_minutes') >= $span) {
                    $validator->errors()->add('break_minutes', 'The break must be shorter than the shift.');
                }
            },
        ];
    }
}
