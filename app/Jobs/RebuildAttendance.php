<?php

namespace App\Jobs;

use App\Models\Employee;
use App\Services\Attendance\AttendanceProcessor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Rebuild one employee's attendance for a date range. Rebuilds of the same employee never run at once.
 */
class RebuildAttendance implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(public int $employeeId, public string $from, public string $to) {}

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("attendance-employee-{$this->employeeId}"))->releaseAfter(10)->expireAfter(300)];
    }

    public function handle(AttendanceProcessor $processor): void
    {
        $employee = Employee::withTrashed()->find($this->employeeId);

        if ($employee) {
            $processor->rebuild($employee, CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to));
        }
    }
}
