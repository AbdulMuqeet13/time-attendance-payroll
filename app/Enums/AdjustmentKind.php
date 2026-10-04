<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AdjustmentKind: string
{
    use HasOptions;

    case Bonus = 'bonus';
    case Allowance = 'allowance';
    case Commission = 'commission';
    case Arrears = 'arrears';
    case Fine = 'fine';
    case Deduction = 'deduction';

    /**
     * Bonuses, allowances, commission and arrears are paid; fines and other deductions are taken off.
     */
    public function isEarning(): bool
    {
        return in_array($this, [self::Bonus, self::Allowance, self::Commission, self::Arrears], true);
    }
}
