<?php

use App\Enums\AttendanceStatus;
use App\Enums\PunchSource;
use App\Enums\RoleEnum;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
});

/**
 * An employee on a 09:00–17:00 shift every day.
 */
function dayShiftEmployee(array $attributes = []): Employee
{
    $employee = Employee::factory()->create(['joining_date' => '2026-01-01', ...$attributes]);
    ShiftAssignment::factory()->for($employee)->for(Shift::factory()->between('09:00', '17:00', 'Day'))->create();

    return $employee;
}

function dayRow(Employee $employee, string $date): AttendanceDay
{
    return AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->sole();
}

test('a manager adds a missed punch and the day is rebuilt at once', function () {
    $employee = dayShiftEmployee();
    AttendancePunch::factory()->for($employee)->at('2026-10-05 09:00')->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.punches.store'), ['employee_id' => $employee->id, 'punched_at' => '2026-10-05 17:05', 'reason' => 'Device was offline'])
        ->assertSessionHasNoErrors();

    expect(AttendancePunch::where('source', PunchSource::Manual)->sole()->reason)->toBe('Device was offline')
        ->and(dayRow($employee, '2026-10-05'))
        ->status->toBe(AttendanceStatus::Present)
        ->last_out->format('H:i')->toBe('17:05');
});

test('a voided punch is kept but ignored', function () {
    $employee = dayShiftEmployee();
    $wrong = AttendancePunch::factory()->for($employee)->at('2026-10-05 09:00')->create();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.punches.void', $wrong), ['reason' => 'Scanned for a colleague']);

    expect($wrong->fresh()->voided_at)->not->toBeNull()
        ->and(dayRow($employee, '2026-10-05')->status)->toBe(AttendanceStatus::Absent);
});

test('a manager marks an absent day present with a reason', function () {
    $employee = dayShiftEmployee();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.days.decide', dayRow($employee, '2026-10-05')), ['status' => 'present', 'reason' => 'Field visit'])
        ->assertSessionHasNoErrors();

    expect(dayRow($employee, '2026-10-05'))->status->toBe(AttendanceStatus::Present)->note->toBe('Field visit');
});

test('only present, half day and absent can be set by hand', function () {
    $employee = dayShiftEmployee();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.days.decide', dayRow($employee, '2026-10-05')), ['status' => 'holiday', 'reason' => 'x'])
        ->assertSessionHasErrors('status');
});

test('payroll-locked days cannot be corrected', function () {
    $employee = dayShiftEmployee();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));
    AttendanceDay::query()->update(['locked_at' => now()]);

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.days.decide', dayRow($employee, '2026-10-05')), ['status' => 'present', 'reason' => 'x'])
        ->assertForbidden();

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('attendance.punches.store'), ['employee_id' => $employee->id, 'punched_at' => '2026-10-05 09:00', 'reason' => 'x'])
        ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'This attendance is part of an approved payroll and can no longer be changed.']);
});

test('a branch manager approves part of the overtime of their branch only', function () {
    $branch = Branch::factory()->create();
    $employee = dayShiftEmployee(['branch_id' => $branch->id]);
    AttendancePunch::factory()->for($employee)->at('2026-10-05 09:00')->create();
    AttendancePunch::factory()->for($employee)->at('2026-10-05 19:00')->create();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));
    $day = dayRow($employee, '2026-10-05');

    $this->actingAs(userWithRole(RoleEnum::BranchManager, ['branch_id' => Branch::factory()->create()->id]))
        ->post(route('overtime.decide', $day), ['approved_minutes' => 60])
        ->assertForbidden();

    $this->actingAs(userWithRole(RoleEnum::BranchManager, ['branch_id' => $branch->id]))
        ->post(route('overtime.decide', $day), ['approved_minutes' => 90])
        ->assertSessionHasNoErrors();

    expect(dayRow($employee, '2026-10-05')->approved_overtime_minutes)->toBe(90);
});

test('more overtime than was worked cannot be approved', function () {
    $employee = dayShiftEmployee();
    AttendancePunch::factory()->for($employee)->at('2026-10-05 09:00')->create();
    AttendancePunch::factory()->for($employee)->at('2026-10-05 18:00')->create();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('overtime.decide', dayRow($employee, '2026-10-05')), ['approved_minutes' => 61])
        ->assertSessionHasErrors('approved_minutes');
});

test('adding a holiday rebuilds the past day for everyone', function () {
    $employee = dayShiftEmployee();
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'));

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->post(route('holidays.store'), ['date' => '2026-10-05', 'name' => 'Special']);

    expect(dayRow($employee, '2026-10-05')->status)->toBe(AttendanceStatus::Holiday);
});

test('the monthly register shows one status per day and totals', function () {
    $employee = dayShiftEmployee();
    Holiday::factory()->create(['date' => '2026-10-02']);
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-03'));

    $this->actingAs(userWithRole(RoleEnum::HrManager))
        ->get(route('attendance.register', ['month' => '2026-10']))
        ->assertInertia(fn ($page) => $page
            ->component('attendance/register')
            ->has('dates', 31)
            ->where('rows.0.cells.2026-10-02.status', 'holiday')
            ->where('rows.0.totals.absent', 2));
});
