<?php

namespace App\Services\Payroll;

use App\Enums\AdjustmentKind;

/**
 * Everything the calculator needs for one employee and period, gathered by PayrollService.
 *
 * Attendance "units" are days, weighted by shift length when a day has several shifts
 * (absent for a 4-hour shift out of 8 scheduled hours = 0.5).
 */
final readonly class PayslipInput
{
    /**
     * @param  array<int, array{id: int, name: string, amount: string}>  $earningComponents
     * @param  array<int, array{id: int, name: string, amount: string}>  $deductionComponents
     * @param  array<int, array{id: int, kind: AdjustmentKind, name: string, amount: string}>  $adjustments
     * @param  array<int, array{id: int, installment: string, remaining: string}>  $advances
     */
    public function __construct(
        public array $earningComponents,
        public array $deductionComponents,
        public int $periodDays,
        public int $employedDays,
        /** Working days in the period by roster (only used with the "scheduled days" basis). */
        public int $scheduledDays,
        public int $employedScheduledDays,
        public float $presentUnits = 0,
        public float $absentUnits = 0,
        public float $halfDayUnits = 0,
        public float $paidLeaveUnits = 0,
        public float $unpaidLeaveUnits = 0,
        public int $holidays = 0,
        public int $weeklyOffs = 0,
        public int $lateCount = 0,
        public int $lateMinutes = 0,
        public int $shortMinutes = 0,
        public int $overtimeMinutes = 0,
        public int $holidayOvertimeMinutes = 0,
        public int $workedMinutes = 0,
        public array $adjustments = [],
        public array $advances = [],
    ) {}
}
