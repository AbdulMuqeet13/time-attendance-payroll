<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum EmploymentType: string
{
    use HasOptions;

    case Permanent = 'permanent';
    case Contract = 'contract';
    case Probation = 'probation';
    case Intern = 'intern';
}
