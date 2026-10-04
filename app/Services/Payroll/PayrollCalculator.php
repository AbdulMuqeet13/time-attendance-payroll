<?php

namespace App\Services\Payroll;

use App\Enums\DayBasis;
use App\Enums\LatePolicy;
use App\Enums\PayslipItemCategory;
use App\Models\PayrollAdjustment;
use App\Models\PayslipItem;
use App\Models\SalaryAdvance;
use App\Models\SalaryComponent;
use App\Support\Money;

/**
 * Turns a fixed monthly salary and the month's attendance into a payslip. Pure: no database access.
 *
 *  per-day rate  = gross ÷ divisor (calendar days in the period, a fixed 30, or scheduled working days)
 *  per-hour rate = per-day rate ÷ standard hours per day
 *
 *  Earnings:   salary components (in full), overtime × hourly × rate, holiday/off-day overtime × hourly ×
 *              holiday rate, bonuses / allowances / commission / arrears
 *  Deductions: days before joining or after leaving, absences, half days (½ day), unpaid leave, lates
 *              (per minute or every N lates = X days), early leaving (optional), fixed monthly deductions
 *              (e.g. tax), fines / other deductions, advance installments (only out of what is left to pay)
 *
 * If deductions still exceed earnings the net is 0 and the difference is reported as a shortfall.
 */
class PayrollCalculator
{
    public function calculate(PayslipInput $input, PayrollPolicy $policy): PayslipResult
    {
        $gross = Money::add('0', ...array_column($input->earningComponents, 'amount'));
        $divisor = $this->divisor($input, $policy);
        $perDay = Money::div($gross, $divisor);
        $perHour = Money::div($perDay, $policy->standardHoursPerDay);
        $perMinute = Money::div($perHour, 60);

        $items = [];

        foreach ($input->earningComponents as $component) {
            $items[] = $this->line(PayslipItem::EARNING, PayslipItemCategory::Component, $component['name'], null, null, null, $component['amount'], SalaryComponent::class, $component['id']);
        }

        if ($input->overtimeMinutes > 0) {
            $hours = Money::div((string) $input->overtimeMinutes, 60);
            $rate = Money::mul($perHour, $policy->overtimeRate);
            $items[] = $this->line(PayslipItem::EARNING, PayslipItemCategory::Overtime, "Overtime (×{$policy->overtimeRate})", $hours, 'hours', $rate, Money::mul($hours, $rate));
        }

        if ($input->holidayOvertimeMinutes > 0) {
            $hours = Money::div((string) $input->holidayOvertimeMinutes, 60);
            $rate = Money::mul($perHour, $policy->overtimeHolidayRate);
            $items[] = $this->line(PayslipItem::EARNING, PayslipItemCategory::HolidayOvertime, "Holiday / off-day overtime (×{$policy->overtimeHolidayRate})", $hours, 'hours', $rate, Money::mul($hours, $rate));
        }

        foreach ($input->adjustments as $adjustment) {
            $items[] = $this->line(
                $adjustment['kind']->isEarning() ? PayslipItem::EARNING : PayslipItem::DEDUCTION,
                PayslipItemCategory::Adjustment,
                $adjustment['name'],
                null,
                null,
                null,
                $adjustment['amount'],
                PayrollAdjustment::class,
                $adjustment['id'],
            );
        }

        $unemployedAmount = $this->unemployedDeduction($input, $policy, $gross, $perDay);

        if (Money::isPositive($unemployedAmount)) {
            $units = $policy->dayBasis === DayBasis::ScheduledDays
                ? $input->scheduledDays - $input->employedScheduledDays
                : $input->periodDays - $input->employedDays;
            $items[] = $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::UnemployedDays, 'Days before joining / after leaving', (string) $units, 'days', $perDay, $unemployedAmount);
        }

        $dayDeductions = [
            [PayslipItemCategory::Absence, 'Absent', $input->absentUnits, $perDay],
            [PayslipItemCategory::HalfDay, 'Half days', $input->halfDayUnits, Money::div($perDay, 2)],
            [PayslipItemCategory::UnpaidLeave, 'Unpaid leave', $input->unpaidLeaveUnits, $perDay],
        ];

        foreach ($dayDeductions as [$category, $name, $units, $rate]) {
            if ($units > 0) {
                $quantity = (string) $units;
                $items[] = $this->line(PayslipItem::DEDUCTION, $category, $name, $quantity, 'days', $rate, Money::mul($rate, $quantity));
            }
        }

        if ($late = $this->lateLine($input, $policy, $perDay, $perMinute)) {
            $items[] = $late;
        }

        if ($policy->deductShortHours && $input->shortMinutes > 0) {
            $hours = Money::div((string) $input->shortMinutes, 60);
            $items[] = $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::ShortHours, 'Left early', $hours, 'hours', $perHour, Money::mul($hours, $perHour));
        }

        foreach ($input->deductionComponents as $component) {
            $items[] = $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::FixedDeduction, $component['name'], null, null, null, $component['amount'], SalaryComponent::class, $component['id']);
        }

        $earnings = $this->total($items, PayslipItem::EARNING);
        $deductions = $this->total($items, PayslipItem::DEDUCTION);
        $available = Money::sub($earnings, $deductions);

        $recoveries = [];

        foreach ($input->advances as $advance) {
            $amount = Money::round(Money::min(Money::min($advance['installment'], $advance['remaining']), Money::max('0', $available)));

            if (Money::isPositive($amount)) {
                $recoveries[$advance['id']] = $amount;
                $available = Money::sub($available, $amount);
                $items[] = $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::Advance, 'Advance installment', null, null, null, $amount, SalaryAdvance::class, $advance['id']);
            }
        }

        $deductions = $this->total($items, PayslipItem::DEDUCTION);
        $net = Money::sub($earnings, $deductions);
        $shortfall = Money::isNegative($net) ? Money::round(Money::sub('0', $net)) : '0.00';

        return new PayslipResult(
            dayDivisor: Money::round($divisor),
            perDayRate: Money::round($perDay, 4),
            perHourRate: Money::round($perHour, 4),
            items: $items,
            earningsTotal: $earnings,
            deductionsTotal: $deductions,
            netPay: Money::isNegative($net) ? '0.00' : Money::round($net),
            shortfall: $shortfall,
            advanceRecoveries: $recoveries,
        );
    }

    private function divisor(PayslipInput $input, PayrollPolicy $policy): string
    {
        return match ($policy->dayBasis) {
            DayBasis::CalendarDays => (string) $input->periodDays,
            DayBasis::Fixed30 => '30',
            DayBasis::ScheduledDays => (string) max(1, $input->scheduledDays),
        };
    }

    /**
     * Pay only covers days on the payroll: the gross for a full period, otherwise per-day rate × days employed
     * (never more than the gross, e.g. 31 days on a fixed-30 basis).
     */
    private function unemployedDeduction(PayslipInput $input, PayrollPolicy $policy, string $gross, string $perDay): string
    {
        [$employed, $total] = $policy->dayBasis === DayBasis::ScheduledDays
            ? [$input->employedScheduledDays, $input->scheduledDays]
            : [$input->employedDays, $input->periodDays];

        if ($employed >= $total) {
            return '0';
        }

        return Money::sub($gross, Money::min($gross, Money::mul($perDay, $employed)));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function lateLine(PayslipInput $input, PayrollPolicy $policy, string $perDay, string $perMinute): ?array
    {
        if ($policy->latePolicy === LatePolicy::PerMinute && $input->lateMinutes > 0) {
            return $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::Late, 'Late arrivals', (string) $input->lateMinutes, 'minutes', $perMinute, Money::mul($perMinute, $input->lateMinutes));
        }

        if ($policy->latePolicy === LatePolicy::CountBased && $input->lateCount >= $policy->latesPerDeduction) {
            $days = Money::mul((string) intdiv($input->lateCount, $policy->latesPerDeduction), $policy->lateDeductionDays);

            return $this->line(PayslipItem::DEDUCTION, PayslipItemCategory::Late, "Late arrivals ({$input->lateCount} lates, every {$policy->latesPerDeduction} = {$policy->lateDeductionDays} day)", $days, 'days', $perDay, Money::mul($perDay, $days));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function line(string $side, PayslipItemCategory $category, string $name, ?string $quantity, ?string $unit, ?string $rate, string $amount, ?string $sourceType = null, ?int $sourceId = null): array
    {
        return [
            'side' => $side,
            'category' => $category,
            'name' => $name,
            'quantity' => $quantity === null ? null : Money::round($quantity),
            'unit' => $unit,
            'rate' => $rate === null ? null : Money::round($rate, 4),
            'amount' => Money::round($amount),
            'source_type' => $sourceType,
            'source_id' => $sourceId,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function total(array $items, string $side): string
    {
        return Money::round(Money::add('0', ...array_column(array_filter($items, fn (array $item) => $item['side'] === $side), 'amount')));
    }
}
