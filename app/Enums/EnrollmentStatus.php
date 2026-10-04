<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EnrollmentStatus: string
{
    use HasOptions;

    case Queued = 'queued';
    case OnDevice = 'on_device';
    case Removing = 'removing';
    case Removed = 'removed';
    case Failed = 'failed';
}
