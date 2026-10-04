<?php

namespace App\Http\Requests\Shifts;

use App\Enums\PermissionEnum;
use App\Models\Holiday;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::HolidaysManage->value);
    }

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')->whereNull('deleted_at')],
        ];
    }

    /**
     * The unique index can't stop two company-wide holidays on one date (NULL branch), so check here.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $exists = Holiday::query()
                    ->whereDate('date', $this->input('date'))
                    ->where('branch_id', $this->input('branch_id'))
                    ->when($this->route('holiday'), fn ($query, Holiday $holiday) => $query->whereKeyNot($holiday->id))
                    ->exists();

                if ($exists) {
                    $validator->errors()->add('date', 'There is already a holiday on this date.');
                }
            },
        ];
    }
}
