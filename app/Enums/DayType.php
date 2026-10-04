<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DayType: string
{
    use HasOptions;

    case Working = 'working';
    case WeeklyOff = 'weekly_off';
    case Holiday = 'holiday';
}
