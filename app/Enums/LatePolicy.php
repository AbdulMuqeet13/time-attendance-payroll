<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LatePolicy: string
{
    use HasOptions;

    /** Lates are tracked but never deducted. */
    case None = 'none';

    /** Each late minute is deducted at the hourly rate. */
    case PerMinute = 'per_minute';

    /** Every N lates in the month deduct a set number of days. */
    case CountBased = 'count_based';
}
