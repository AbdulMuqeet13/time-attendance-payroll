<?php

namespace App\Services\Attendance;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\RosterOverride;
use App\Models\ShiftAssignment;
use Carbon\CarbonInterface;

class ShiftResolver
{
    /**
     * Load the employee's roster for the dates (one day of margin each side for overnight shifts).
     */
    public function scheduleFor(Employee $employee, CarbonInterface $from, CarbonInterface $to): EmployeeSchedule
    {
        $from = $from->copy()->subDay();
        $to = $to->copy()->addDay();

        $assignments = ShiftAssignment::query()
            ->where('employee_id', $employee->id)
            ->effectiveBetween($from, $to)
            ->with('shift')
            ->get();

        $overrides = RosterOverride::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->with('shift')
            ->get()
            ->keyBy(fn (RosterOverride $override) => $override->date->toDateString());

        $holidays = Holiday::query()
            ->forBranch($employee->branch_id)
            ->between($from, $to)
            ->get()
            ->keyBy(fn (Holiday $holiday) => $holiday->date->toDateString());

        return new EmployeeSchedule($employee, $assignments, $overrides, $holidays);
    }
}
