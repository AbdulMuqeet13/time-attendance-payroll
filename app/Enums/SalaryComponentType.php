<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum SalaryComponentType: string
{
    use HasOptions;

    case Earning = 'earning';
    case Deduction = 'deduction';
}
