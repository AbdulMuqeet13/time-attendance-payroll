<?php

namespace App\Http\Requests\Shifts;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Rules shared by creating and editing a shift assignment.
 */
final class ShiftAssignmentRules
{
    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public static function rules(): array
    {
        return [
            'shift_id' => ['required', 'integer', Rule::exists('shifts', 'id')->whereNull('deleted_at')],
            'days' => ['nullable', 'array', 'max:7'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
