<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PayrollStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';
}
