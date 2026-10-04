<?php

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\RoleEnum;
use App\Models\AttendanceDay;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\ShiftAssignment;
use App\Services\Leaves\LeaveBalanceService;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
});

/**
 * Monday–Saturday employee who joined before this year.
 */
function sixDayEmployee(array $attributes = []): Employee
{
    $employee = Employee::factory()->create(['joining_date' => '2025-01-01', ...$attributes]);
    ShiftAssignment::factory()->for($employee)->mondayToSaturday()->create(['effective_from' => '2025-01-01']);

    return $employee;
}

function annualLeave(): LeaveType
{
    return LeaveType::query()->where('code', 'AL')->sole();
}

test('leave days skip weekly offs and holidays', function () {
    $employee = sixDayEmployee();
    Holiday::factory()->create(['date' => '2026-10-07']);

    // Mon 5th to Sun 11th: 7 days, minus Sunday and the holiday
    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.store'), ['employee_id' => $employee->id, 'leave_type_id' => annualLeave()->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-11'])
        ->assertSessionHasNoErrors();

    expect(LeaveRequest::sole())->days->toBe('5.0')->status->toBe(LeaveStatus::Pending);
});

test('a request beyond the available balance is refused with the numbers', function () {
    $employee = sixDayEmployee();
    LeaveRequest::factory()->for($employee)->for(annualLeave())->approved()->between('2026-03-02', '2026-03-13', 12)->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.store'), ['employee_id' => $employee->id, 'leave_type_id' => annualLeave()->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07'])
        ->assertSessionHasErrors(['start_date' => 'Only 2 day(s) of Annual Leave are available, this needs 3.']);
});

test('unpaid leave has no balance limit', function () {
    $employee = sixDayEmployee();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.store'), ['employee_id' => $employee->id, 'leave_type_id' => LeaveType::where('code', 'UL')->value('id'), 'start_date' => '2026-10-05', 'end_date' => '2026-10-31'])
        ->assertSessionHasNoErrors();
});

test('overlapping requests are refused', function () {
    $employee = sixDayEmployee();
    LeaveRequest::factory()->for($employee)->for(annualLeave())->create(['start_date' => '2026-10-06', 'end_date' => '2026-10-06']);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.store'), ['employee_id' => $employee->id, 'leave_type_id' => annualLeave()->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-07'])
        ->assertSessionHasErrors(['start_date' => 'This overlaps another leave request.']);
});

test('a half day must be a single date', function () {
    $employee = sixDayEmployee();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.store'), ['employee_id' => $employee->id, 'leave_type_id' => annualLeave()->id, 'start_date' => '2026-10-05', 'end_date' => '2026-10-06', 'is_half_day' => true])
        ->assertSessionHasErrors(['start_date' => 'A half day must start and end on the same date.']);
});

test('approving leave marks those past days as leave', function () {
    $employee = sixDayEmployee();
    $leave = LeaveRequest::factory()->for($employee)->for(annualLeave())->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-05']);

    $this->actingAs(userWithRole(RoleEnum::HrManager))->post(route('leaves.approve', $leave))->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved)
        ->and(AttendanceDay::where('employee_id', $employee->id)->whereDate('date', '2026-10-05')->sole()->status)->toBe(AttendanceStatus::Leave);
});

test('a branch manager cannot decide leave for another branch', function () {
    $leave = LeaveRequest::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::BranchManager, ['branch_id' => Branch::factory()->create()->id]))
        ->post(route('leaves.approve', $leave))
        ->assertForbidden();
});

test('rejecting needs a reason', function () {
    $leave = LeaveRequest::factory()->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.reject', $leave), ['note' => ''])
        ->assertSessionHasErrors('note');
});

test('approved leave in a payroll-locked period cannot be cancelled', function () {
    $employee = sixDayEmployee();
    $leave = LeaveRequest::factory()->for($employee)->for(annualLeave())->approved()->create();
    AttendanceDay::factory()->for($employee)->create(['date' => '2026-10-05', 'locked_at' => now()]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('leaves.cancel', $leave))
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'These days are part of an approved payroll and can no longer change.']);

    expect($leave->fresh()->status)->toBe(LeaveStatus::Approved);
});

test('mid-year joiners get a pro-rated quota and unused days carry forward up to the limit', function (string $joined, float $expected) {
    $employee = Employee::factory()->create(['joining_date' => $joined]);

    expect(app(LeaveBalanceService::class)->balanceFor($employee, annualLeave(), 2026)->entitled)->toBe(number_format($expected, 1));
})->with([
    'joined last year' => ['2025-06-01', 14],
    'joined in July' => ['2026-07-10', 7],
    'joined in December' => ['2026-12-01', 1],
]);

test('unused annual leave carries forward up to its limit', function () {
    $employee = Employee::factory()->create(['joining_date' => '2024-01-01']);
    LeaveBalance::query()->create(['employee_id' => $employee->id, 'leave_type_id' => annualLeave()->id, 'year' => 2025, 'entitled' => 14]);
    LeaveRequest::factory()->for($employee)->for(annualLeave())->approved()->between('2025-05-05', '2025-05-07', 3)->create();

    expect(app(LeaveBalanceService::class)->balanceFor($employee, annualLeave(), 2026)->carried_forward)->toBe('7.0');
});
