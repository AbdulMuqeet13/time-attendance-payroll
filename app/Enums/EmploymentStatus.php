<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmploymentStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Suspended = 'suspended';
    case Resigned = 'resigned';
    case Terminated = 'terminated';
}
