<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PayslipItemCategory: string
{
    use HasOptions;

    case Component = 'component';
    case Overtime = 'overtime';
    case HolidayOvertime = 'holiday_overtime';
    case Adjustment = 'adjustment';
    case UnemployedDays = 'unemployed_days';
    case Absence = 'absence';
    case HalfDay = 'half_day';
    case UnpaidLeave = 'unpaid_leave';
    case Late = 'late';
    case ShortHours = 'short_hours';
    case FixedDeduction = 'fixed_deduction';
    case Advance = 'advance';
}
