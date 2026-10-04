<?php

namespace App\Services\Attendance;

use App\Jobs\RebuildAttendance;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Queues attendance rebuilds after something that affects past days changes
 * (punches, roster, holidays, leave, overrides).
 */
class AttendanceRebuilder
{
    /**
     * Rebuild from a day before the earliest date (a punch after midnight may close yesterday's
     * overnight shift) up to the latest date, never beyond today.
     */
    public function forEmployee(int|Employee $employee, CarbonInterface|string $from, CarbonInterface|string|null $to = null): void
    {
        $to = CarbonImmutable::parse($to ?? $from)->min(CarbonImmutable::today());
        $from = CarbonImmutable::parse($from)->subDay();

        if ($from->gt($to)) {
            return;
        }

        RebuildAttendance::dispatch($employee instanceof Employee ? $employee->id : $employee, $from->toDateString(), $to->toDateString());
    }

    /**
     * Rebuild every employee of a branch (or every employee when branch is null) for the dates.
     */
    public function forBranch(?int $branchId, CarbonInterface|string $from, CarbonInterface|string|null $to = null): void
    {
        Employee::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->employedBetween(CarbonImmutable::parse($from), CarbonImmutable::parse($to ?? $from))
            ->pluck('id')
            ->each(fn (int $employeeId) => $this->forEmployee($employeeId, $from, $to));
    }
}
