<?php

use App\Enums\AdjustmentKind;
use App\Enums\AdvanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\RoleEnum;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollRun;
use App\Models\SalaryAdvance;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00:00'));
});

/**
 * A 60,000/month employee on a Mon–Sat 09:00–17:00 roster who scanned in and out every working day
 * of October 2026 except the given dates.
 *
 * @param  array<int, string>  $absentDates
 */
function octoberEmployee(array $absentDates = [], array $attributes = []): Employee
{
    $employee = Employee::factory()->withSalary('60000.00', '2025-01-01')->create(['joining_date' => '2025-01-01', ...$attributes]);
    ShiftAssignment::factory()->for($employee)->for(Shift::factory()->between('09:00', '17:00', 'Day'))->mondayToSaturday()->create();

    foreach (CarbonPeriod::create('2026-10-01', '2026-10-31') as $date) {
        if ($date->isSunday() || in_array($date->toDateString(), $absentDates, true)) {
            continue;
        }

        AttendancePunch::factory()->for($employee)->at($date->toDateString().' 08:55')->create();
        AttendancePunch::factory()->for($employee)->at($date->toDateString().' 17:05')->create();
    }

    return $employee;
}

function generateOctober(array $extra = []): TestResponse
{
    return test()->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->post(route('payroll.store'), ['period_start' => '2026-10-01', 'period_end' => '2026-10-31', ...$extra]);
}

test('a payroll run pays the salary less absences found in attendance', function () {
    $employee = octoberEmployee(['2026-10-07', '2026-10-08']);

    generateOctober()->assertSessionHasNoErrors();

    $run = PayrollRun::sole();
    $payslip = $run->payslips()->sole();

    expect($run)
        ->status->toBe(PayrollStatus::Draft)
        ->reference->toBe('PR-2026-10-001')
        ->employee_count->toBe(1)
        ->and($payslip)
        ->employee_id->toBe($employee->id)
        ->absent_days->toBe('2.0')
        ->present_days->toBe('25.0')
        ->weekly_offs->toBe(4)
        ->net_pay->toBe('56129.03')
        ->and($run->net_total)->toBe('56129.03');
});

test('a second run for the same month is refused', function () {
    octoberEmployee();
    generateOctober();

    generateOctober()->assertSessionHasErrors(['period_start' => 'Run PR-2026-10-001 already covers part of this period.']);
});

test('a company-wide run overlaps any branch run', function () {
    $employee = octoberEmployee();
    generateOctober(['branch_id' => $employee->branch_id]);

    generateOctober()->assertSessionHasErrors('period_start');
});

test('employees without a salary are skipped and named', function () {
    octoberEmployee();
    Employee::factory()->create(['joining_date' => '2025-01-01', 'name' => 'No Salary']);

    generateOctober()->assertInertiaFlash('toast', ['type' => 'warning', 'message' => 'Draft PR-2026-10-001 created. Skipped without a salary: No Salary.']);
});

test('bonuses for the month are paid and marked as used once approved', function () {
    $employee = octoberEmployee();
    $bonus = PayrollAdjustment::factory()->for($employee)->create(['period' => '2026-10-01', 'kind' => AdjustmentKind::Bonus, 'amount' => '5000']);

    generateOctober();
    $run = PayrollRun::sole();

    expect($run->payslips()->sole()->net_pay)->toBe('65000.00');

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))->post(route('payroll.approve', $run))->assertSessionHasNoErrors();

    expect($bonus->fresh()->payroll_run_id)->toBe($run->id);
});

test('approving locks the attendance of the period', function () {
    $employee = octoberEmployee(['2026-10-07']);
    generateOctober();
    $run = PayrollRun::sole();

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))->post(route('payroll.approve', $run));

    expect($run->fresh()->status)->toBe(PayrollStatus::Approved)
        ->and(AttendanceDay::where('employee_id', $employee->id)->whereNull('locked_at')->whereBetween('date', ['2026-10-01', '2026-10-31'])->count())->toBe(0);

    AttendancePunch::factory()->for($employee)->at('2026-10-07 09:00')->create();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-07'), CarbonImmutable::parse('2026-10-07'));

    expect(AttendanceDay::where('employee_id', $employee->id)->whereDate('date', '2026-10-07')->sole()->status->value)->toBe('absent');
});

test('payroll officers cannot approve', function () {
    octoberEmployee();
    generateOctober();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))->post(route('payroll.approve', PayrollRun::sole()))->assertForbidden();
});

test('an approved run can go back to draft only for a super admin, unlocking attendance', function () {
    octoberEmployee();
    generateOctober();
    $run = PayrollRun::sole();
    $admin = userWithRole(RoleEnum::SuperAdmin);
    $this->actingAs($admin)->post(route('payroll.approve', $run));

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))->post(route('payroll.revert', $run))->assertForbidden();
    $this->actingAs($admin)->post(route('payroll.revert', $run))->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollStatus::Draft)
        ->and(AttendanceDay::whereNotNull('locked_at')->count())->toBe(0);
});

test('an advance installment is recovered and the advance settles when fully repaid', function () {
    $employee = octoberEmployee();
    $advance = SalaryAdvance::factory()->for($employee)->create(['amount' => '3000', 'installment_amount' => '3000', 'start_period' => '2026-10-01']);

    generateOctober();
    $run = PayrollRun::sole();

    expect($run->payslips()->sole()->net_pay)->toBe('57000.00');

    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))->post(route('payroll.approve', $run));

    expect($advance->fresh()->status)->toBe(AdvanceStatus::Settled)
        ->and($advance->fresh()->remaining())->toBe('0');
});

test('recording payment of every payslip marks the run paid', function () {
    octoberEmployee();
    generateOctober();
    $run = PayrollRun::sole();
    $this->actingAs(userWithRole(RoleEnum::SuperAdmin))->post(route('payroll.approve', $run));

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->post(route('payroll.pay', $run), ['paid_on' => '2026-11-05', 'reference' => 'BANK-123'])
        ->assertSessionHasNoErrors();

    expect($run->fresh()->status)->toBe(PayrollStatus::Paid)
        ->and($run->payslips()->sole()->payment_status)->toBe(PaymentStatus::Paid);
});

test('a cancelled draft frees the period for a new run', function () {
    octoberEmployee();
    generateOctober();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))->post(route('payroll.cancel', PayrollRun::sole()));
    generateOctober()->assertSessionHasNoErrors();

    expect(PayrollRun::where('status', PayrollStatus::Draft)->sole()->reference)->toBe('PR-2026-10-002');
});

test('a branch run only includes that branch', function () {
    $included = octoberEmployee();
    octoberEmployee();

    generateOctober(['branch_id' => $included->branch_id]);

    expect(PayrollRun::sole()->payslips()->pluck('employee_id')->all())->toBe([$included->id]);
});

test('the payslip PDF and bank sheet download', function () {
    octoberEmployee();
    generateOctober();
    $run = PayrollRun::sole();
    $user = userWithRole(RoleEnum::PayrollOfficer);

    $this->actingAs($user)->get(route('payroll.payslips.pdf', [$run, $run->payslips()->sole()]))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs($user)->get(route('payroll.bank-sheet', $run))->assertOk()->assertDownload("bank-transfer-{$run->reference}.xlsx");
});

test('the run page lists its payslips with their lines', function () {
    octoberEmployee();
    generateOctober();

    $this->actingAs(userWithRole(RoleEnum::PayrollOfficer))
        ->get(route('payroll.show', PayrollRun::sole()))
        ->assertInertia(fn ($page) => $page->component('payroll/show')->has('payslips', 1)->has('payslips.0.items'));
});
