<?php

namespace App\Services\Payroll;

use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Support\Settings;

/**
 * The payroll rules in force for a run (copied onto the run so a regenerated draft uses the same rules).
 */
final readonly class PayrollPolicy
{
    public function __construct(
        public DayBasis $dayBasis = DayBasis::CalendarDays,
        public float $standardHoursPerDay = 8,
        public LatePolicy $latePolicy = LatePolicy::CountBased,
        public int $latesPerDeduction = 3,
        public float $lateDeductionDays = 1,
        public bool $deductShortHours = false,
        public float $overtimeRate = 1.5,
        public float $overtimeHolidayRate = 2.0,
    ) {}

    public static function fromSettings(Settings $settings): self
    {
        return new self(
            $settings->dayBasis(),
            $settings->standardHoursPerDay(),
            $settings->latePolicy(),
            $settings->latesPerDeduction(),
            $settings->lateDeductionDays(),
            $settings->deductShortHours(),
            $settings->overtimeRate(),
            $settings->overtimeHolidayRate(),
        );
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fromArray(array $values): self
    {
        return new self(
            DayBasis::from($values['day_basis']),
            (float) $values['standard_hours_per_day'],
            LatePolicy::from($values['late_policy']),
            (int) $values['lates_per_deduction'],
            (float) $values['late_deduction_days'],
            (bool) $values['deduct_short_hours'],
            (float) $values['overtime_rate'],
            (float) $values['overtime_holiday_rate'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'day_basis' => $this->dayBasis->value,
            'standard_hours_per_day' => $this->standardHoursPerDay,
            'late_policy' => $this->latePolicy->value,
            'lates_per_deduction' => $this->latesPerDeduction,
            'late_deduction_days' => $this->lateDeductionDays,
            'deduct_short_hours' => $this->deductShortHours,
            'overtime_rate' => $this->overtimeRate,
            'overtime_holiday_rate' => $this->overtimeHolidayRate,
        ];
    }
}
