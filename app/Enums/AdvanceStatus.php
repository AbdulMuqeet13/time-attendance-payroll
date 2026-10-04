<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AdvanceStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Settled = 'settled';
    case Cancelled = 'cancelled';
}
