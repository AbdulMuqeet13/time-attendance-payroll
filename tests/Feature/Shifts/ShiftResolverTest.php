<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterOverride;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\ShiftOccurrence;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;

test('assigned shifts apply only on their weekdays and dates', function () {
    $employee = Employee::factory()->create();
    ShiftAssignment::factory()->for($employee)->mondayToSaturday()->create(['effective_from' => '2026-10-01', 'effective_to' => '2026-10-31']);

    $schedule = app(ShiftResolver::class)->scheduleFor($employee, CarbonImmutable::parse('2026-09-30'), CarbonImmutable::parse('2026-11-02'));

    expect($schedule->occurrencesOn(CarbonImmutable::parse('2026-10-05')))->toHaveCount(1) // Monday
        ->and($schedule->occurrencesOn(CarbonImmutable::parse('2026-10-04')))->toBeEmpty() // Sunday
        ->and($schedule->occurrencesOn(CarbonImmutable::parse('2026-09-30')))->toBeEmpty() // before
        ->and($schedule->occurrencesOn(CarbonImmutable::parse('2026-11-02')))->toBeEmpty(); // after
});

test('an overnight occurrence ends the next day and its window adds the early and grace minutes', function () {
    $employee = Employee::factory()->create();
    ShiftAssignment::factory()->for($employee)->for(Shift::factory()->night()->create(['early_window_minutes' => 30, 'checkout_grace_minutes' => 60]))->create();

    /** @var ShiftOccurrence $occurrence */
    $occurrence = app(ShiftResolver::class)
        ->scheduleFor($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-05'))
        ->occurrencesOn(CarbonImmutable::parse('2026-10-05'))
        ->sole();

    expect($occurrence->endsAt->toDateTimeString())->toBe('2026-10-06 06:00:00')
        ->and($occurrence->windowStart->toDateTimeString())->toBe('2026-10-05 21:30:00')
        ->and($occurrence->windowEnd->toDateTimeString())->toBe('2026-10-06 07:00:00')
        ->and($occurrence->scheduledMinutes())->toBe(480);
});

test('a roster override replaces the assigned shift or gives the day off', function () {
    $employee = Employee::factory()->create();
    ShiftAssignment::factory()->for($employee)->create();
    $evening = Shift::factory()->between('14:00', '22:00', 'Evening')->create();
    RosterOverride::factory()->for($employee)->create(['date' => '2026-10-05', 'shift_id' => $evening->id]);
    RosterOverride::factory()->for($employee)->create(['date' => '2026-10-06', 'shift_id' => null]);

    $schedule = app(ShiftResolver::class)->scheduleFor($employee, CarbonImmutable::parse('2026-10-05'), CarbonImmutable::parse('2026-10-06'));

    expect($schedule->occurrencesOn(CarbonImmutable::parse('2026-10-05'))->sole()->shift->name)->toBe('Evening')
        ->and($schedule->occurrencesOn(CarbonImmutable::parse('2026-10-06')))->toBeEmpty()
        ->and($schedule->isDayOffOverride(CarbonImmutable::parse('2026-10-06')))->toBeTrue();
});

test('company-wide and own-branch holidays apply, other branches do not', function () {
    $employee = Employee::factory()->create();
    Holiday::factory()->create(['date' => '2026-08-14', 'name' => 'Independence Day']);
    Holiday::factory()->create(['date' => '2026-08-15', 'branch_id' => $employee->branch_id, 'name' => 'Local']);
    Holiday::factory()->create(['date' => '2026-08-16', 'branch_id' => Branch::factory()]);

    $schedule = app(ShiftResolver::class)->scheduleFor($employee, CarbonImmutable::parse('2026-08-14'), CarbonImmutable::parse('2026-08-16'));

    expect($schedule->holidayOn(CarbonImmutable::parse('2026-08-14'))?->name)->toBe('Independence Day')
        ->and($schedule->holidayOn(CarbonImmutable::parse('2026-08-15'))?->name)->toBe('Local')
        ->and($schedule->holidayOn(CarbonImmutable::parse('2026-08-16')))->toBeNull();
});
