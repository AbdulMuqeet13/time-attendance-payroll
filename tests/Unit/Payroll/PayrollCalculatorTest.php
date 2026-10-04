<?php

use App\Enums\AdjustmentKind;
use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Enums\PayslipItemCategory;
use App\Services\Payroll\PayrollCalculator;
use App\Services\Payroll\PayrollPolicy;
use App\Services\Payroll\PayslipInput;
use App\Services\Payroll\PayslipResult;

/**
 * October 2026 (31 days), 60,000 basic salary, employed all month, unless overridden.
 *
 * @param  array<string, mixed>  $overrides
 */
function payslip(array $overrides = [], ?PayrollPolicy $policy = null): PayslipResult
{
    $input = new PayslipInput(...[
        'earningComponents' => [['id' => 1, 'name' => 'Basic Salary', 'amount' => '60000.00']],
        'deductionComponents' => [],
        'periodDays' => 31,
        'employedDays' => 31,
        'scheduledDays' => 27,
        'employedScheduledDays' => 27,
        ...$overrides,
    ]);

    return (new PayrollCalculator)->calculate($input, $policy ?? new PayrollPolicy);
}

/**
 * @return array<string, string> category value => amount
 */
function amounts(PayslipResult $result): array
{
    return collect($result->items)->mapWithKeys(fn (array $item) => [$item['category']->value => $item['amount']])->all();
}

test('full attendance pays the gross salary', function () {
    $result = payslip(['presentUnits' => 27]);

    expect($result->netPay)->toBe('60000.00')
        ->and($result->deductionsTotal)->toBe('0.00')
        ->and($result->perDayRate)->toBe('1935.4839')
        ->and($result->perHourRate)->toBe('241.9355');
});

test('absences, half days and unpaid leave are deducted at the per-day rate', function () {
    $result = payslip(['absentUnits' => 2, 'halfDayUnits' => 2, 'unpaidLeaveUnits' => 1]);

    expect(amounts($result))->toMatchArray([
        'absence' => '3870.97',
        'half_day' => '1935.48',
        'unpaid_leave' => '1935.48',
    ])->and($result->netPay)->toBe('52258.07');
});

test('the divisor follows the day basis setting', function (DayBasis $basis, string $absenceDeduction) {
    $result = payslip(['absentUnits' => 1], new PayrollPolicy(dayBasis: $basis));

    expect(amounts($result)['absence'])->toBe($absenceDeduction);
})->with([
    'calendar days (31)' => [DayBasis::CalendarDays, '1935.48'],
    'fixed 30' => [DayBasis::Fixed30, '2000.00'],
    'scheduled days (27)' => [DayBasis::ScheduledDays, '2222.22'],
]);

test('a mid-month joiner is paid for the days employed', function (DayBasis $basis, array $days, string $deduction) {
    $result = payslip($days, new PayrollPolicy(dayBasis: $basis));

    expect(amounts($result)['unemployed_days'])->toBe($deduction);
})->with([
    'calendar: 16 of 31 days' => [DayBasis::CalendarDays, ['employedDays' => 16], '29032.26'],
    'fixed 30: 16 days' => [DayBasis::Fixed30, ['employedDays' => 16], '28000.00'],
    'scheduled: 14 of 27 working days' => [DayBasis::ScheduledDays, ['employedScheduledDays' => 14], '28888.89'],
]);

test('a full 31-day month on a fixed-30 basis pays exactly the gross', function () {
    expect(payslip([], new PayrollPolicy(dayBasis: DayBasis::Fixed30))->netPay)->toBe('60000.00');
});

test('every N lates deduct days of pay', function () {
    $result = payslip(['lateCount' => 7, 'lateMinutes' => 140], new PayrollPolicy(latePolicy: LatePolicy::CountBased, latesPerDeduction: 3, lateDeductionDays: 1));

    expect(amounts($result)['late'])->toBe('3870.97');
});

test('fewer lates than the threshold deduct nothing', function () {
    $result = payslip(['lateCount' => 2], new PayrollPolicy(latePolicy: LatePolicy::CountBased, latesPerDeduction: 3));

    expect(amounts($result))->not->toHaveKey('late');
});

test('late minutes can be deducted at the per-minute rate', function () {
    $result = payslip(['lateCount' => 3, 'lateMinutes' => 90], new PayrollPolicy(latePolicy: LatePolicy::PerMinute));

    expect(amounts($result)['late'])->toBe('362.90');
});

test('early leaving is deducted only when the setting is on', function (bool $deduct, ?string $expected) {
    $result = payslip(['shortMinutes' => 120], new PayrollPolicy(deductShortHours: $deduct));

    expect(amounts($result)['short_hours'] ?? null)->toBe($expected);
})->with([
    'on' => [true, '483.87'],
    'off' => [false, null],
]);

test('approved overtime is paid at the overtime and holiday rates', function () {
    $result = payslip(['overtimeMinutes' => 600, 'holidayOvertimeMinutes' => 240], new PayrollPolicy(overtimeRate: 1.5, overtimeHolidayRate: 2.0));

    expect(amounts($result))->toMatchArray([
        'overtime' => '3629.03',
        'holiday_overtime' => '1935.48',
    ])->and($result->earningsTotal)->toBe('65564.51');
});

test('bonuses add, fines and fixed deductions such as tax subtract', function () {
    $result = payslip([
        'deductionComponents' => [['id' => 5, 'name' => 'Income Tax', 'amount' => '1500.00']],
        'adjustments' => [
            ['id' => 1, 'kind' => AdjustmentKind::Bonus, 'name' => 'Eid bonus', 'amount' => '5000.00'],
            ['id' => 2, 'kind' => AdjustmentKind::Fine, 'name' => 'Uniform', 'amount' => '500.00'],
        ],
    ]);

    expect($result->earningsTotal)->toBe('65000.00')
        ->and($result->deductionsTotal)->toBe('2000.00')
        ->and($result->netPay)->toBe('63000.00');
});

test('an advance installment is recovered, capped at what is left on the advance', function () {
    $result = payslip(['advances' => [
        ['id' => 1, 'installment' => '3000.00', 'remaining' => '10000.00'],
        ['id' => 2, 'installment' => '5000.00', 'remaining' => '1200.00'],
    ]]);

    expect($result->advanceRecoveries)->toBe([1 => '3000.00', 2 => '1200.00'])
        ->and($result->netPay)->toBe('55800.00');
});

test('advance installments never push pay below zero', function () {
    $result = payslip([
        'absentUnits' => 30,
        'advances' => [['id' => 1, 'installment' => '5000.00', 'remaining' => '5000.00']],
    ]);

    expect($result->advanceRecoveries)->toBe([1 => '1935.48'])
        ->and($result->netPay)->toBe('0.00')
        ->and($result->shortfall)->toBe('0.00');
});

test('deductions larger than earnings give zero pay and a shortfall', function () {
    $result = payslip(['adjustments' => [['id' => 1, 'kind' => AdjustmentKind::Fine, 'name' => 'Damage', 'amount' => '70000.00']]]);

    expect($result->netPay)->toBe('0.00')
        ->and($result->shortfall)->toBe('10000.00');
});

test('every line explains itself as quantity × rate', function () {
    $absence = collect(payslip(['absentUnits' => 2])->items)->firstWhere('category', PayslipItemCategory::Absence);

    expect($absence)->toMatchArray(['quantity' => '2.00', 'unit' => 'days', 'rate' => '1935.4839', 'amount' => '3870.97']);
});
