<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * What the monthly salary is divided by to get the per-day rate.
 */
enum DayBasis: string
{
    use HasOptions;

    case CalendarDays = 'calendar_days';
    case Fixed30 = 'fixed_30';
    case ScheduledDays = 'scheduled_days';
}
