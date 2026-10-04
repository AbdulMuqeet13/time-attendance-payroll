<?php

namespace App\Http\Requests\Organisation;

use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Enums\PermissionEnum;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can(PermissionEnum::SettingsManage->value);
    }

    /**
     * Keys use the dotted setting names with dots replaced by "__" so they survive form encoding.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'company__name' => ['required', 'string', 'max:255'],
            'company__address' => ['nullable', 'string', 'max:500'],
            'company__phone' => ['nullable', 'string', 'max:50'],

            'attendance__late_grace_minutes' => ['required', 'integer', 'min:0', 'max:240'],
            'attendance__early_window_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'attendance__checkout_grace_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'attendance__duplicate_scan_minutes' => ['required', 'integer', 'min:0', 'max:60'],
            'attendance__half_day_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'attendance__min_overtime_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'attendance__overtime_requires_approval' => ['required', 'boolean'],

            'payroll__day_basis' => ['required', Rule::enum(DayBasis::class)],
            'payroll__standard_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],
            'payroll__late_policy' => ['required', Rule::enum(LatePolicy::class)],
            'payroll__lates_per_deduction' => ['required', 'integer', 'min:1', 'max:31'],
            'payroll__late_deduction_days' => ['required', 'numeric', 'min:0', 'max:5'],
            'payroll__deduct_short_hours' => ['required', 'boolean'],
            'payroll__overtime_rate' => ['required', 'numeric', 'min:0', 'max:5'],
            'payroll__overtime_holiday_rate' => ['required', 'numeric', 'min:0', 'max:5'],
        ];
    }

    /**
     * Validated values keyed by their setting names, e.g. "payroll.day_basis".
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        return collect($this->validated())
            ->mapWithKeys(fn (mixed $value, string $key) => [str_replace('__', '.', $key) => $value])
            ->all();
    }
}
