<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterOverride;
use App\Models\ShiftAssignment;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * An employee's roster for a date range, loaded once: shift assignments, one-day overrides and holidays.
 */
final class EmployeeSchedule
{
    /**
     * @param  Collection<int, ShiftAssignment>  $assignments
     * @param  Collection<string, RosterOverride>  $overrides  keyed by Y-m-d
     * @param  Collection<string, Holiday>  $holidays  keyed by Y-m-d
     */
    public function __construct(
        public readonly Employee $employee,
        private Collection $assignments,
        private Collection $overrides,
        private Collection $holidays,
    ) {}

    /**
     * Shift occurrences that start on the date. A roster override replaces the assignments for that day.
     *
     * @return Collection<int, ShiftOccurrence>
     */
    public function occurrencesOn(CarbonInterface $date): Collection
    {
        $date = CarbonImmutable::parse($date->toDateString());
        $override = $this->overrides->get($date->toDateString());

        if ($override) {
            return $override->shift ? collect([new ShiftOccurrence($override->shift, $date)]) : collect();
        }

        return $this->assignments
            ->filter(fn (ShiftAssignment $assignment) => $assignment->appliesOn($date))
            ->map(fn (ShiftAssignment $assignment) => new ShiftOccurrence($assignment->shift, $date))
            ->sortBy(fn (ShiftOccurrence $occurrence) => $occurrence->startsAt)
            ->values();
    }

    /**
     * Occurrences that start the day before, on, or the day after the date — enough to place any punch
     * near midnight against overnight shifts.
     *
     * @return Collection<int, ShiftOccurrence>
     */
    public function occurrencesAround(CarbonInterface $date): Collection
    {
        $date = CarbonImmutable::parse($date->toDateString());

        return collect([$date->subDay(), $date, $date->addDay()])
            ->flatMap(fn (CarbonImmutable $day) => $this->occurrencesOn($day))
            ->values();
    }

    /**
     * Whether the employee has a roster on the date at all. With an assignment in effect but no shift that
     * weekday the day is a weekly off; with no assignment at all it is unscheduled.
     */
    public function hasRosterOn(CarbonInterface $date): bool
    {
        if ($this->overrides->has($date->toDateString())) {
            return true;
        }

        $day = $date->toDateString();

        return $this->assignments->contains(fn (ShiftAssignment $assignment) => $assignment->effective_from->toDateString() <= $day
            && ($assignment->effective_to === null || $assignment->effective_to->toDateString() >= $day));
    }

    public function holidayOn(CarbonInterface $date): ?Holiday
    {
        return $this->holidays->get($date->toDateString());
    }

    /**
     * Whether the date was explicitly made a day off by a roster override.
     */
    public function isDayOffOverride(CarbonInterface $date): bool
    {
        $override = $this->overrides->get($date->toDateString());

        return $override !== null && $override->shift_id === null;
    }
}
