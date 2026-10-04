<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PaymentMethod: string
{
    use HasOptions;

    case Bank = 'bank';
    case Cash = 'cash';
}
