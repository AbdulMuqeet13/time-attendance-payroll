<?php

namespace App\Services\Payroll;

use App\Enums\AdvanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Exceptions\Payroll\PayrollException;
use App\Models\AdvanceRecovery;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryAdvance;
use App\Models\User;
use App\Services\Attendance\AttendanceProcessor;
use App\Support\Money;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Payroll runs: generate (draft), regenerate, approve (locks attendance, uses adjustments and advance
 * installments), revert to draft, mark paid, cancel.
 */
class PayrollService
{
    public function __construct(
        private PayrollCalculator $calculator,
        private PayslipInputBuilder $inputBuilder,
        private AttendanceProcessor $attendance,
        private Settings $settings,
    ) {}

    /**
     * Create a draft run and its payslips. Attendance for the period is recalculated first.
     *
     * @return array{run: PayrollRun, skipped: array<int, string>}
     *
     * @throws PayrollException When another run already covers these employees and dates
     */
    public function generate(CarbonImmutable $periodStart, CarbonImmutable $periodEnd, ?int $branchId, User $user, ?string $notes = null): array
    {
        $run = DB::transaction(function () use ($periodStart, $periodEnd, $branchId, $user, $notes) {
            if (PayrollRun::query()->overlapping($periodStart, $periodEnd, $branchId)->lockForUpdate()->exists()) {
                throw new PayrollException('A payroll run already covers part of this period for these employees. Cancel it first or pick another period.');
            }

            return PayrollRun::query()->create([
                'reference' => $this->nextReference($periodStart),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
                'branch_id' => $branchId,
                'status' => PayrollStatus::Draft,
                'notes' => $notes,
                'settings_snapshot' => PayrollPolicy::fromSettings($this->settings)->toArray(),
                'created_by' => $user->id,
            ]);
        });

        $skipped = $this->buildPayslips($run);

        return ['run' => $run->fresh(), 'skipped' => $skipped];
    }

    /**
     * Throw the draft payslips away and build them again (after attendance, salary or adjustment changes).
     *
     * @return array<int, string> Names of employees skipped for having no salary
     *
     * @throws PayrollException
     */
    public function regenerate(PayrollRun $run): array
    {
        $this->ensureDraft($run);

        $run->payslips()->delete();

        return $this->buildPayslips($run);
    }

    /**
     * Approve the run: attendance of its employees for the period is locked, adjustments are marked as paid by
     * this run, and advances that are now fully recovered are settled.
     *
     * @throws PayrollException
     */
    public function approve(PayrollRun $run, User $user): void
    {
        DB::transaction(function () use ($run, $user) {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->id);
            $this->ensureDraft($run);

            if ($run->payslips()->doesntExist()) {
                throw new PayrollException('There are no payslips to approve.');
            }

            $run->update(['status' => PayrollStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()]);

            $employeeIds = $run->payslips()->pluck('employee_id');

            AttendanceDay::query()
                ->whereIn('employee_id', $employeeIds)
                ->between($run->period_start, $run->period_end)
                ->update(['locked_at' => now()]);

            PayrollAdjustment::query()
                ->whereIn('id', $run->payslips()->join('payslip_items', 'payslip_items.payslip_id', '=', 'payslips.id')
                    ->where('payslip_items.source_type', PayrollAdjustment::class)->pluck('payslip_items.source_id'))
                ->update(['payroll_run_id' => $run->id]);

            $this->settleAdvances($run);
        });
    }

    /**
     * Undo an approval so the run can be corrected (unpaid runs only).
     *
     * @throws PayrollException
     */
    public function revertToDraft(PayrollRun $run): void
    {
        DB::transaction(function () use ($run) {
            if ($run->status !== PayrollStatus::Approved || $run->payslips()->where('payment_status', PaymentStatus::Paid)->exists()) {
                throw new PayrollException('Only an approved run with no payslips paid can go back to draft.');
            }

            $run->update(['status' => PayrollStatus::Draft, 'approved_by' => null, 'approved_at' => null]);

            AttendanceDay::query()
                ->whereIn('employee_id', $run->payslips()->pluck('employee_id'))
                ->between($run->period_start, $run->period_end)
                ->update(['locked_at' => null]);

            PayrollAdjustment::query()->where('payroll_run_id', $run->id)->update(['payroll_run_id' => null]);

            SalaryAdvance::query()
                ->where('status', AdvanceStatus::Settled)
                ->whereHas('recoveries.payslip', fn ($query) => $query->where('payroll_run_id', $run->id))
                ->update(['status' => AdvanceStatus::Active]);
        });
    }

    /**
     * Record payment of some payslips (or all of them). The run is paid once every payslip is.
     *
     * @param  array<int, int>|null  $payslipIds
     *
     * @throws PayrollException
     */
    public function markPaid(PayrollRun $run, ?array $payslipIds, CarbonImmutable $paidOn, ?string $reference): int
    {
        if (! in_array($run->status, [PayrollStatus::Approved, PayrollStatus::Paid], true)) {
            throw new PayrollException('Approve the run before recording payments.');
        }

        $count = $run->payslips()
            ->when($payslipIds, fn ($query, array $ids) => $query->whereKey($ids))
            ->where('payment_status', PaymentStatus::Unpaid)
            ->update(['payment_status' => PaymentStatus::Paid, 'paid_at' => $paidOn, 'payment_reference' => $reference]);

        if ($run->payslips()->where('payment_status', PaymentStatus::Unpaid)->doesntExist()) {
            $run->update(['status' => PayrollStatus::Paid, 'paid_at' => $paidOn]);
        }

        return $count;
    }

    /**
     * @throws PayrollException
     */
    public function cancel(PayrollRun $run): void
    {
        $this->ensureDraft($run);

        DB::transaction(function () use ($run) {
            $run->payslips()->delete();
            $run->update(['status' => PayrollStatus::Cancelled, 'cancelled_at' => now(), 'employee_count' => 0, 'earnings_total' => 0, 'deductions_total' => 0, 'net_total' => 0]);
        });
    }

    /**
     * @return array<int, string> Names of employees skipped for having no salary
     */
    private function buildPayslips(PayrollRun $run): array
    {
        $policy = PayrollPolicy::fromArray($run->settings_snapshot ?? PayrollPolicy::fromSettings($this->settings)->toArray());
        $periodStart = CarbonImmutable::parse($run->period_start->toDateString());
        $periodEnd = CarbonImmutable::parse($run->period_end->toDateString());
        $skipped = [];

        Employee::query()
            ->when($run->branch_id, fn ($query, int $branchId) => $query->where('branch_id', $branchId))
            ->employedBetween($periodStart, $periodEnd)
            ->with(['department:id,name', 'designation:id,name', 'branch:id,name'])
            ->orderBy('employee_code')
            ->chunkById(100, function ($employees) use ($run, $policy, $periodStart, $periodEnd, &$skipped) {
                foreach ($employees as $employee) {
                    $salary = $employee->salaryEffectiveOn($employee->exit_date && $employee->exit_date->lt($periodEnd) ? $employee->exit_date : $periodEnd);

                    if (! $salary) {
                        $skipped[] = $employee->name;

                        continue;
                    }

                    $this->attendance->rebuild($employee, $periodStart, $periodEnd);
                    $this->createPayslip($run, $employee, $salary, $policy);
                }
            });

        $this->refreshTotals($run);

        return $skipped;
    }

    private function createPayslip(PayrollRun $run, Employee $employee, EmployeeSalary $salary, PayrollPolicy $policy): void
    {
        [$input] = $this->inputBuilder->build($employee, $salary, $run);
        $result = $this->calculator->calculate($input, $policy);

        DB::transaction(function () use ($run, $employee, $salary, $input, $result) {
            $payslip = Payslip::query()->create([
                'payroll_run_id' => $run->id,
                'employee_id' => $employee->id,
                'employee_salary_id' => $salary->id,
                'employee_code' => $employee->employee_code,
                'employee_name' => $employee->name,
                'department' => $employee->department?->name,
                'designation' => $employee->designation?->name,
                'branch' => $employee->branch->name,
                'payment_method' => $employee->payment_method,
                'bank_name' => $employee->bank_name,
                'account_title' => $employee->account_title,
                'account_number' => $employee->account_number,
                'monthly_gross' => Money::add('0', ...array_column($input->earningComponents, 'amount')),
                'day_divisor' => $result->dayDivisor,
                'per_day_rate' => $result->perDayRate,
                'per_hour_rate' => $result->perHourRate,
                'period_days' => $input->periodDays,
                'employed_days' => $input->employedDays,
                'scheduled_days' => $input->employedScheduledDays,
                'present_days' => $input->presentUnits,
                'absent_days' => $input->absentUnits,
                'half_days' => $input->halfDayUnits,
                'paid_leave_days' => $input->paidLeaveUnits,
                'unpaid_leave_days' => $input->unpaidLeaveUnits,
                'holidays' => $input->holidays,
                'weekly_offs' => $input->weeklyOffs,
                'late_count' => $input->lateCount,
                'late_minutes' => $input->lateMinutes,
                'short_minutes' => $input->shortMinutes,
                'overtime_minutes' => $input->overtimeMinutes,
                'holiday_overtime_minutes' => $input->holidayOvertimeMinutes,
                'worked_minutes' => $input->workedMinutes,
                'earnings_total' => $result->earningsTotal,
                'deductions_total' => $result->deductionsTotal,
                'net_pay' => $result->netPay,
                'shortfall' => $result->shortfall,
                'payment_status' => PaymentStatus::Unpaid,
            ]);

            foreach ($result->items as $order => $item) {
                $payslip->items()->create([...$item, 'sort_order' => $order]);
            }

            foreach ($result->advanceRecoveries as $advanceId => $amount) {
                AdvanceRecovery::query()->create([
                    'salary_advance_id' => $advanceId,
                    'payslip_id' => $payslip->id,
                    'amount' => $amount,
                    'recovered_on' => $run->period_end->toDateString(),
                ]);
            }
        });
    }

    private function refreshTotals(PayrollRun $run): void
    {
        $run->update([
            'employee_count' => $run->payslips()->count(),
            'earnings_total' => (string) $run->payslips()->sum('earnings_total'),
            'deductions_total' => (string) $run->payslips()->sum('deductions_total'),
            'net_total' => (string) $run->payslips()->sum('net_pay'),
        ]);
    }

    private function settleAdvances(PayrollRun $run): void
    {
        SalaryAdvance::query()
            ->where('status', AdvanceStatus::Active)
            ->whereHas('recoveries.payslip', fn ($query) => $query->where('payroll_run_id', $run->id))
            ->get()
            ->filter(fn (SalaryAdvance $advance) => ! Money::isPositive($advance->remaining()))
            ->each(fn (SalaryAdvance $advance) => $advance->update(['status' => AdvanceStatus::Settled]));
    }

    /**
     * @throws PayrollException
     */
    private function ensureDraft(PayrollRun $run): void
    {
        if ($run->status !== PayrollStatus::Draft) {
            throw new PayrollException('Only draft payroll runs can be changed.');
        }
    }

    private function nextReference(CarbonImmutable $periodStart): string
    {
        $prefix = 'PR-'.$periodStart->format('Y-m').'-';
        $count = PayrollRun::query()->where('reference', 'like', $prefix.'%')->count();

        return $prefix.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }
}
