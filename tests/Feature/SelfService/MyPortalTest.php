<?php

use App\Enums\AttendanceStatus;
use App\Enums\RoleEnum;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @return array{0: User, 1: Employee}
 */
function employeeLogin(): array
{
    $user = userWithRole(RoleEnum::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id, 'joining_date' => '2025-01-01']);

    return [$user, $employee];
}

test('employees land on their own portal instead of the dashboard', function () {
    [$user] = employeeLogin();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('my.index'));
});

test('employees cannot open admin pages', function () {
    [$user] = employeeLogin();

    $this->actingAs($user)->get(route('employees.index'))->assertForbidden();
    $this->actingAs($user)->get(route('payroll.index'))->assertForbidden();
});

test('the portal shows the employee their own leave and balances', function () {
    [$user, $employee] = employeeLogin();
    $annual = LeaveType::where('code', 'AL')->sole();
    LeaveRequest::factory()->for($employee)->for($annual)->create();
    LeaveRequest::factory()->for($annual)->create();

    $this->actingAs($user)->get(route('my.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/index')
            ->where('employee.id', $employee->id)
            ->has('leaveRequests', 1)
            ->has('balances', 3));
});

test('an employee applies for leave for themselves', function () {
    [$user, $employee] = employeeLogin();
    ShiftAssignment::factory()->for($employee)->mondayToSaturday()->create();
    $other = Employee::factory()->create();

    $this->actingAs($user)
        ->post(route('leaves.store'), [
            'employee_id' => $other->id,
            'leave_type_id' => LeaveType::where('code', 'CL')->value('id'),
            'start_date' => '2026-12-07', 'end_date' => '2026-12-07',
        ])
        ->assertSessionHasNoErrors();

    expect(LeaveRequest::sole()->employee_id)->toBe($employee->id);
});

test('an employee cannot download someone else\'s payslip or a draft one', function () {
    [$user, $employee] = employeeLogin();
    $run = PayrollRun::query()->create(['reference' => 'PR-1', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'status' => 'draft']);
    $payslipAttributes = fn (Employee $owner) => [
        'payroll_run_id' => $run->id, 'employee_id' => $owner->id, 'employee_code' => $owner->employee_code, 'employee_name' => $owner->name,
        'payment_method' => 'bank', 'monthly_gross' => 1, 'day_divisor' => 31, 'per_day_rate' => 1, 'per_hour_rate' => 1,
        'period_days' => 31, 'employed_days' => 31, 'earnings_total' => 1, 'deductions_total' => 0, 'net_pay' => 1,
    ];
    $own = Payslip::query()->create($payslipAttributes($employee));
    $someoneElses = Payslip::query()->create($payslipAttributes(Employee::factory()->create()));

    $this->actingAs($user)->get(route('my.payslips.pdf', $own))->assertNotFound();

    $run->update(['status' => 'approved']);

    $this->actingAs($user)->get(route('my.payslips.pdf', $someoneElses))->assertNotFound();
    $this->actingAs($user)->get(route('my.payslips.pdf', $own))->assertOk();
});

test('the dashboard shows HR today\'s attendance and the 30-day trend', function () {
    $employee = Employee::factory()->create();
    AttendanceDay::factory()->for($employee)->for(Shift::factory())->status(AttendanceStatus::Late)->create(['date' => today()->subDay()->toDateString()]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('today.employees')
            ->where('payroll', null)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                ->has('trend', 30)
                ->where('trend.28.late', 1)));
});
