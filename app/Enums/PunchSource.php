<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PunchSource: string
{
    use HasOptions;

    case Device = 'device';
    case Manual = 'manual';
    case Import = 'import';
    case Restore = 'restore';
}
