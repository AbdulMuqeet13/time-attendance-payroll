<?php

namespace App\Observers;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Services\Adms\PunchRecorder;
use App\Services\Biometrics\BiometricSyncService;

class EmployeeObserver
{
    public function __construct(private PunchRecorder $punchRecorder, private BiometricSyncService $biometricSync) {}

    /**
     * When an employee gets a device PIN, earlier scans with that PIN become theirs.
     */
    public function saved(Employee $employee): void
    {
        if ($employee->wasChanged('device_pin') || ($employee->wasRecentlyCreated && $employee->device_pin !== null)) {
            $this->punchRecorder->linkUnmatchedPunches($employee);
        }

        if ($employee->wasChanged('status') && in_array($employee->status, [EmploymentStatus::Resigned, EmploymentStatus::Terminated], true)) {
            $this->biometricSync->removeFromAllDevices($employee);
        }
    }

    /**
     * A deleted employee can no longer scan in.
     */
    public function deleted(Employee $employee): void
    {
        $this->biometricSync->removeFromAllDevices($employee);
    }
}
