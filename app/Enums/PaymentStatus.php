<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentStatus: string
{
    use HasOptions;

    case Unpaid = 'unpaid';
    case Paid = 'paid';
}
