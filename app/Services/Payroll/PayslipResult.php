<?php

namespace App\Services\Payroll;

use App\Enums\PayslipItemCategory;

/**
 * The calculated payslip: rates, every line, totals, and the advance installments taken.
 */
final readonly class PayslipResult
{
    /**
     * @param  array<int, array{side: string, category: PayslipItemCategory, name: string, quantity: string|null, unit: string|null, rate: string|null, amount: string, source_type: string|null, source_id: int|null}>  $items
     * @param  array<int, string>  $advanceRecoveries  advance id => amount
     */
    public function __construct(
        public string $dayDivisor,
        public string $perDayRate,
        public string $perHourRate,
        public array $items,
        public string $earningsTotal,
        public string $deductionsTotal,
        public string $netPay,
        public string $shortfall,
        public array $advanceRecoveries,
    ) {}
}
