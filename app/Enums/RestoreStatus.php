<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum RestoreStatus: string
{
    use HasOptions;

    case Running = 'running';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
}
