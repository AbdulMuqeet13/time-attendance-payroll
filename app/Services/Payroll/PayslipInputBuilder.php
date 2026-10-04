<?php

namespace App\Services\Payroll;

use App\Enums\AdjustmentKind;
use App\Enums\AdvanceStatus;
use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Enums\SalaryComponentType;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Collects one employee's salary, attendance, adjustments and advances for a payroll period.
 */
class PayslipInputBuilder
{
    /**
     * @return array{0: PayslipInput, 1: CarbonImmutable, 2: CarbonImmutable} The input and the employed part of the period
     */
    public function build(Employee $employee, EmployeeSalary $salary, PayrollRun $run): array
    {
        $periodStart = CarbonImmutable::parse($run->period_start->toDateString());
        $periodEnd = CarbonImmutable::parse($run->period_end->toDateString());
        $employedFrom = $periodStart->max(CarbonImmutable::parse($employee->joining_date->toDateString()));
        $employedTo = $employee->exit_date ? $periodEnd->min(CarbonImmutable::parse($employee->exit_date->toDateString())) : $periodEnd;

        $components = $salary->components()->with('salaryComponent')->get();
        $toLine = fn (EmployeeSalaryComponent $component) => [
            'id' => $component->salary_component_id,
            'name' => $component->salaryComponent->name,
            'amount' => (string) $component->amount,
        ];

        $days = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->between($employedFrom, $employedTo)
            ->get();

        $totals = $this->attendanceTotals($days);
        [$scheduledDays, $employedScheduledDays] = $this->scheduledDays($days, $periodStart, $periodEnd, $employedFrom, $employedTo);

        $input = new PayslipInput(...[
            'earningComponents' => $components->filter(fn ($component) => $component->salaryComponent->type === SalaryComponentType::Earning)->map($toLine)->values()->all(),
            'deductionComponents' => $components->filter(fn ($component) => $component->salaryComponent->type === SalaryComponentType::Deduction)->map($toLine)->values()->all(),
            'periodDays' => (int) $periodStart->diffInDays($periodEnd) + 1,
            'employedDays' => $employedFrom->lte($employedTo) ? (int) $employedFrom->diffInDays($employedTo) + 1 : 0,
            'scheduledDays' => $scheduledDays,
            'employedScheduledDays' => $employedScheduledDays,
            ...$totals,
            'adjustments' => $this->adjustments($employee, $run),
            'advances' => $this->advances($employee, $run),
        ]);

        return [$input, $employedFrom, $employedTo];
    }

    /**
     * Day counts weighted by shift length when a date has several shifts.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @return array<string, int|float>
     */
    private function attendanceTotals(Collection $days): array
    {
        $totals = [
            'presentUnits' => 0.0, 'absentUnits' => 0.0, 'halfDayUnits' => 0.0, 'paidLeaveUnits' => 0.0, 'unpaidLeaveUnits' => 0.0,
            'holidays' => 0, 'weeklyOffs' => 0, 'lateCount' => 0, 'lateMinutes' => 0, 'shortMinutes' => 0,
            'overtimeMinutes' => 0, 'holidayOvertimeMinutes' => 0, 'workedMinutes' => 0,
        ];

        foreach ($days->groupBy(fn (AttendanceDay $day) => $day->date->toDateString()) as $rows) {
            $scheduled = $rows->whereNotNull('shift_id');
            $totalScheduled = max(1, $scheduled->sum('scheduled_minutes'));

            if ($rows->contains('day_type', DayType::Holiday)) {
                $totals['holidays']++;
            } elseif ($scheduled->isEmpty() && $rows->contains('status', AttendanceStatus::WeeklyOff)) {
                $totals['weeklyOffs']++;
            }

            foreach ($rows as $row) {
                $share = $row->shift_id ? $row->scheduled_minutes / $totalScheduled : 0;
                $leave = (float) $row->leave_fraction * $share;

                match ($row->status) {
                    AttendanceStatus::Absent => $totals['absentUnits'] += $share,
                    AttendanceStatus::HalfDay => $totals['halfDayUnits'] += $share,
                    AttendanceStatus::Present, AttendanceStatus::Late, AttendanceStatus::Scheduled => $totals['presentUnits'] += $share,
                    default => null,
                };

                if ($leave > 0) {
                    $totals[$row->leave_is_paid ? 'paidLeaveUnits' : 'unpaidLeaveUnits'] += $leave;
                }

                if ($row->status === AttendanceStatus::Late) {
                    $totals['lateCount']++;
                    $totals['lateMinutes'] += $row->late_minutes;
                }

                $totals['shortMinutes'] += $row->early_leave_minutes;
                $totals['workedMinutes'] += $row->worked_minutes;
                $approved = $row->approved_overtime_minutes ?? 0;
                $totals[$row->day_type === DayType::Working ? 'overtimeMinutes' : 'holidayOvertimeMinutes'] += $approved;
            }
        }

        foreach (['presentUnits', 'absentUnits', 'halfDayUnits', 'paidLeaveUnits', 'unpaidLeaveUnits'] as $key) {
            $totals[$key] = round($totals[$key], 2);
        }

        return $totals;
    }

    /**
     * Working days in the period: dates with a shift while employed; outside employment every day but Sunday.
     *
     * @param  Collection<int, AttendanceDay>  $days
     * @return array{0: int, 1: int}
     */
    private function scheduledDays(Collection $days, CarbonImmutable $periodStart, CarbonImmutable $periodEnd, CarbonImmutable $employedFrom, CarbonImmutable $employedTo): array
    {
        $scheduledDates = $days->whereNotNull('shift_id')->map(fn (AttendanceDay $day) => $day->date->toDateString())->unique()->flip();
        $employed = 0;
        $outside = 0;

        foreach (CarbonPeriod::create($periodStart, $periodEnd) as $date) {
            $isEmployed = $date->betweenIncluded($employedFrom, $employedTo);

            if ($isEmployed) {
                $employed += $scheduledDates->has($date->toDateString()) ? 1 : 0;
            } else {
                $outside += $date->isSunday() ? 0 : 1;
            }
        }

        return [$employed + $outside, $employed];
    }

    /**
     * @return array<int, array{id: int, kind: AdjustmentKind, name: string, amount: string}>
     */
    private function adjustments(Employee $employee, PayrollRun $run): array
    {
        return PayrollAdjustment::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('period', [$run->period_start->copy()->startOfMonth()->toDateString(), $run->period_end->toDateString()])
            ->where(fn ($query) => $query->whereNull('payroll_run_id')->orWhere('payroll_run_id', $run->id))
            ->orderBy('id')
            ->get()
            ->map(fn (PayrollAdjustment $adjustment) => [
                'id' => $adjustment->id,
                'kind' => $adjustment->kind,
                'name' => $adjustment->name,
                'amount' => (string) $adjustment->amount,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, installment: string, remaining: string}>
     */
    private function advances(Employee $employee, PayrollRun $run): array
    {
        return SalaryAdvance::query()
            ->where('employee_id', $employee->id)
            ->where('status', AdvanceStatus::Active)
            ->where('start_period', '<=', $run->period_end->toDateString())
            ->orderBy('issued_on')
            ->get()
            ->map(fn (SalaryAdvance $advance) => [
                'id' => $advance->id,
                'installment' => (string) $advance->installment_amount,
                'remaining' => $advance->remaining($run->id),
            ])
            ->filter(fn (array $advance) => (float) $advance['remaining'] > 0)
            ->values()
            ->all();
    }
}
