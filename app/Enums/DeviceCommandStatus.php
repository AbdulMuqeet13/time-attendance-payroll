<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeviceCommandStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Sent = 'sent';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
