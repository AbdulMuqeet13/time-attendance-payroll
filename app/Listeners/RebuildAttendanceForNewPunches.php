<?php

namespace App\Listeners;

use App\Events\PunchesRecorded;
use App\Services\Attendance\AttendanceRebuilder;

class RebuildAttendanceForNewPunches
{
    public function __construct(private AttendanceRebuilder $rebuilder) {}

    public function handle(PunchesRecorded $event): void
    {
        foreach ($event->employeeDates as $employeeId => $dates) {
            sort($dates);
            $this->rebuilder->forEmployee($employeeId, $dates[0], $dates[count($dates) - 1]);
        }
    }
}
