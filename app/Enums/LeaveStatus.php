<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum LeaveStatus: string
{
    use HasOptions;

    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
