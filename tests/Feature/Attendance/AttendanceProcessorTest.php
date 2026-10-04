<?php

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Models\AttendanceDay;
use App\Models\AttendanceOverride;
use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceProcessor;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * An employee working the given shifts every day from 2026-01-01.
 */
function employeeWithShifts(Shift ...$shifts): Employee
{
    $employee = Employee::factory()->create(['joining_date' => '2026-01-01']);

    foreach ($shifts as $shift) {
        ShiftAssignment::factory()->for($employee)->for($shift)->create(['effective_from' => '2026-01-01']);
    }

    return $employee;
}

function scan(Employee $employee, string ...$times): void
{
    foreach ($times as $time) {
        AttendancePunch::factory()->for($employee)->at($time)->create();
    }
}

/**
 * Rebuild one date and return its rows keyed by shift name ("none" for rows without a shift).
 *
 * @return array<string, AttendanceDay>
 */
function rebuildDay(Employee $employee, string $date): array
{
    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse($date), CarbonImmutable::parse($date));

    return AttendanceDay::query()->where('employee_id', $employee->id)->whereDate('date', $date)->with(['shift', 'sessions'])->get()
        ->keyBy(fn (AttendanceDay $day) => $day->shift?->name ?? 'none')
        ->all();
}

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00:00'));
});

test('split shifts: double scans ignored, a scan between shifts is unscheduled, a late evening check-in is late', function () {
    $employee = employeeWithShifts(
        Shift::factory()->between('06:00', '10:00', 'Morning')->create(),
        Shift::factory()->between('17:00', '21:00', 'Evening')->create(),
    );

    scan($employee, '2026-10-05 05:55', '2026-10-05 05:56', '2026-10-05 10:02', '2026-10-05 13:00', '2026-10-05 13:30', '2026-10-05 17:20', '2026-10-05 21:00', '2026-10-05 21:01');

    $days = rebuildDay($employee, '2026-10-05');

    expect($days['Morning'])
        ->status->toBe(AttendanceStatus::Present)
        ->first_in->format('H:i')->toBe('05:55')
        ->last_out->format('H:i')->toBe('10:02')
        ->worked_minutes->toBe(242)
        ->overtime_minutes->toBe(0)
        ->and($days['Morning']->sessions)->toHaveCount(1)
        ->and($days['Evening'])
        ->status->toBe(AttendanceStatus::Late)
        ->late_minutes->toBe(20)
        ->last_out->format('H:i')->toBe('21:00')
        ->and($days['none'])
        ->status->toBe(AttendanceStatus::Unscheduled)
        ->worked_minutes->toBe(30);
});

test('a break inside a shift makes a second session and an untaken break is deducted', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create(['break_minutes' => 60]));

    scan($employee, '2026-10-05 09:20', '2026-10-05 09:50', '2026-10-05 09:55', '2026-10-05 17:00');

    $day = rebuildDay($employee, '2026-10-05')['Day'];

    expect($day->sessions)->toHaveCount(2)
        ->and($day->status)->toBe(AttendanceStatus::Late)
        ->and($day->late_minutes)->toBe(20)
        // 30 + 425 minutes worked, 5 minutes of gap taken as break, 55 of the 60-minute break deducted
        ->and($day->worked_minutes)->toBe(400);
});

test('an overnight shift belongs to the day it starts', function () {
    $employee = employeeWithShifts(Shift::factory()->night()->create());

    scan($employee, '2026-10-05 21:50', '2026-10-06 06:05');

    $days = rebuildDay($employee, '2026-10-05');

    expect($days['Night'])
        ->status->toBe(AttendanceStatus::Present)
        ->last_out->toDateTimeString()->toBe('2026-10-06 06:05:00')
        ->worked_minutes->toBe(485)
        ->overtime_minutes->toBe(0);
});

test('a forgotten check-out is flagged, credited to the shift end, and not closed by the next day\'s scan', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());

    scan($employee, '2026-10-05 09:00', '2026-10-06 08:58');

    $monday = rebuildDay($employee, '2026-10-05')['Day'];
    $tuesday = rebuildDay($employee, '2026-10-06')['Day'];

    expect($monday)
        ->is_missing_checkout->toBeTrue()
        ->worked_minutes->toBe(480)
        ->overtime_minutes->toBe(0)
        ->and($tuesday)
        ->first_in->format('H:i')->toBe('08:58')
        ->is_missing_checkout->toBeTrue();
});

test('back-to-back shifts: checking out of one and straight into the next', function () {
    $employee = employeeWithShifts(
        Shift::factory()->between('06:00', '10:00', 'Early')->create(),
        Shift::factory()->between('10:00', '14:00', 'Late')->create(),
    );

    scan($employee, '2026-10-05 05:58', '2026-10-05 10:00', '2026-10-05 10:01', '2026-10-05 14:00');

    $days = rebuildDay($employee, '2026-10-05');

    expect($days['Early']->last_out->format('H:i'))->toBe('10:00')
        ->and($days['Late']->first_in->format('H:i'))->toBe('10:01')
        ->and($days['Late']->status)->toBe(AttendanceStatus::Present);
});

test('no scans on a scheduled day is absent, but only once the shift window has passed', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    $this->travelTo(CarbonImmutable::parse('2026-10-05 15:00'));

    expect(rebuildDay($employee, '2026-10-05')['Day']->status)->toBe(AttendanceStatus::Scheduled);

    $this->travelTo(CarbonImmutable::parse('2026-10-05 19:01'));

    expect(rebuildDay($employee, '2026-10-05')['Day']->status)->toBe(AttendanceStatus::Absent);
});

test('someone still at work is present, not a half day', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00'));
    scan($employee, '2026-10-05 09:05');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::Present)
        ->worked_minutes->toBe(55);
});

test('leaving after less than the half-day minimum is a half day', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    scan($employee, '2026-10-05 09:00', '2026-10-05 12:00');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::HalfDay)
        ->early_leave_minutes->toBe(0);
});

test('leaving early is recorded on a present day', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    scan($employee, '2026-10-05 09:00', '2026-10-05 16:15');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::Present)
        ->early_leave_minutes->toBe(45);
});

test('overtime after the shift end waits for approval, and an approval survives rebuilds', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    scan($employee, '2026-10-05 09:00', '2026-10-05 19:10');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->overtime_minutes->toBe(130)
        ->approved_overtime_minutes->toBeNull();

    AttendanceOverride::factory()->for($employee)->create(['date' => '2026-10-05', 'shift_id' => $employee->shiftAssignments()->value('shift_id'), 'approved_overtime_minutes' => 120]);

    expect(rebuildDay($employee, '2026-10-05')['Day']->approved_overtime_minutes)->toBe(120);
});

test('overtime is approved automatically when approval is switched off', function () {
    app(Settings::class)->set(['attendance.overtime_requires_approval' => false]);
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    scan($employee, '2026-10-05 09:00', '2026-10-05 18:00');

    expect(rebuildDay($employee, '2026-10-05')['Day']->approved_overtime_minutes)->toBe(60);
});

test('long overtime past the check-out grace still checks out', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create(['checkout_grace_minutes' => 60]));
    scan($employee, '2026-10-05 09:00', '2026-10-05 21:30');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->overtime_minutes->toBe(270)
        ->is_missing_checkout->toBeFalse();
});

test('overtime shorter than the minimum is not counted', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    scan($employee, '2026-10-05 09:00', '2026-10-05 17:25');

    expect(rebuildDay($employee, '2026-10-05')['Day']->overtime_minutes)->toBe(0);
});

test('days without a shift are weekly offs, and work on them is all overtime', function () {
    $employee = Employee::factory()->create(['joining_date' => '2026-01-01']);
    ShiftAssignment::factory()->for($employee)->mondayToSaturday()->create();

    expect(rebuildDay($employee, '2026-10-04')['none']->status)->toBe(AttendanceStatus::WeeklyOff); // Sunday

    scan($employee, '2026-10-04 10:00', '2026-10-04 14:00');

    expect(rebuildDay($employee, '2026-10-04')['none'])
        ->day_type->toBe(DayType::WeeklyOff)
        ->worked_minutes->toBe(240)
        ->overtime_minutes->toBe(240);
});

test('a holiday is never absent, and work on it is holiday overtime', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    Holiday::factory()->create(['date' => '2026-10-05']);

    expect(rebuildDay($employee, '2026-10-05')['Day']->status)->toBe(AttendanceStatus::Holiday);

    scan($employee, '2026-10-05 10:00', '2026-10-05 13:00');

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->day_type->toBe(DayType::Holiday)
        ->overtime_minutes->toBe(180)
        ->late_minutes->toBe(0);
});

test('approved leave covers the day', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    $leave = LeaveRequest::factory()->for($employee)->approved()->create(['start_date' => '2026-10-05', 'end_date' => '2026-10-05']);

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::Leave)
        ->leave_request_id->toBe($leave->id)
        ->leave_is_paid->toBeTrue();
});

test('a half-day leave with no scans is a half day, not absent', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    LeaveRequest::factory()->for($employee)->approved()->create(['is_half_day' => true, 'days' => 0.5]);

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::HalfDay)
        ->leave_fraction->toBe('0.5');
});

test('a manager override and a waived late survive rebuilds', function () {
    $shift = Shift::factory()->between('09:00', '17:00', 'Day')->create();
    $employee = employeeWithShifts($shift);
    scan($employee, '2026-10-06 09:40', '2026-10-06 17:00');
    AttendanceOverride::factory()->for($employee)->create(['date' => '2026-10-05', 'shift_id' => $shift->id, 'status' => AttendanceStatus::Present, 'reason' => 'Client visit']);
    AttendanceOverride::factory()->for($employee)->create(['date' => '2026-10-06', 'shift_id' => $shift->id, 'waive_late' => true]);

    expect(rebuildDay($employee, '2026-10-05')['Day'])
        ->status->toBe(AttendanceStatus::Present)
        ->is_overridden->toBeTrue()
        ->note->toBe('Client visit')
        ->and(rebuildDay($employee, '2026-10-06')['Day'])
        ->status->toBe(AttendanceStatus::Present)
        ->late_minutes->toBe(0);
});

test('an employee with no roster has unscheduled days', function () {
    $employee = Employee::factory()->create(['joining_date' => '2026-01-01']);

    expect(rebuildDay($employee, '2026-10-05')['none']->status)->toBe(AttendanceStatus::Unscheduled);
});

test('days before joining, after leaving, and in the future are not built', function () {
    $employee = employeeWithShifts(Shift::factory()->create());
    $employee->update(['joining_date' => '2026-10-02', 'exit_date' => '2026-10-06']);

    app(AttendanceProcessor::class)->rebuild($employee, CarbonImmutable::parse('2026-09-28'), CarbonImmutable::parse('2026-10-15'));

    expect(AttendanceDay::where('employee_id', $employee->id)->pluck('date')->map->toDateString()->all())
        ->toBe(['2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06']);
});

test('payroll-locked days are not rebuilt', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    rebuildDay($employee, '2026-10-05');
    AttendanceDay::query()->update(['locked_at' => now()]);

    scan($employee, '2026-10-05 09:00', '2026-10-05 17:00');

    expect(rebuildDay($employee, '2026-10-05')['Day']->status)->toBe(AttendanceStatus::Absent);
});

test('a scan pushed by a device rebuilds that employee\'s attendance', function () {
    $employee = employeeWithShifts(Shift::factory()->between('09:00', '17:00', 'Day')->create());
    $employee->update(['device_pin' => '7']);
    $device = Device::factory()->create();

    devicePost("/iclock/cdata?SN={$device->serial_number}&table=ATTLOG", "7\t2026-10-05 09:30:00\t0\t1\n7\t2026-10-05 17:00:00\t1\t1\n");

    expect(AttendanceDay::where('employee_id', $employee->id)->whereDate('date', '2026-10-05')->sole())
        ->status->toBe(AttendanceStatus::Late)
        ->late_minutes->toBe(30);
});
