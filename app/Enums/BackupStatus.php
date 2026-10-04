<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BackupStatus: string
{
    use HasOptions;

    case Collecting = 'collecting';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';
}
