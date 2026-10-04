<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SalaryChangeType: string
{
    use HasOptions;

    case Initial = 'initial';
    case Increment = 'increment';
    case Decrement = 'decrement';
    case Revision = 'revision';
}
