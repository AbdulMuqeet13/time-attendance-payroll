<?php

namespace App\Services\Attendance;

use App\Enums\PunchSource;
use App\Exceptions\Attendance\AttendanceLockedException;
use App\Models\AttendanceDay;
use App\Models\AttendanceOverride;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Manual corrections by managers. Each one rebuilds the affected days straight away so the result shows
 * immediately. Days locked by an approved payroll cannot be corrected.
 */
class AttendanceAdjustments
{
    public function __construct(private AttendanceProcessor $processor) {}

    /**
     * Add a punch the device missed (forgot to scan, device down).
     *
     * @throws AttendanceLockedException
     */
    public function addPunch(Employee $employee, CarbonImmutable $punchedAt, string $reason, User $user): AttendancePunch
    {
        $this->ensureUnlocked($employee, $punchedAt->subDay(), $punchedAt);

        $punch = AttendancePunch::query()->create([
            'employee_id' => $employee->id,
            'pin' => $employee->device_pin,
            'punched_at' => $punchedAt,
            'source' => PunchSource::Manual,
            'dedupe_hash' => sha1('manual|'.Str::uuid()),
            'reason' => $reason,
            'created_by' => $user->id,
        ]);

        $this->processor->rebuild($employee, $punchedAt->subDay(), $punchedAt);

        return $punch;
    }

    /**
     * Discard a wrong punch. It is kept for the audit trail but ignored from now on.
     *
     * @throws AttendanceLockedException
     */
    public function voidPunch(AttendancePunch $punch, string $reason, User $user): void
    {
        $at = CarbonImmutable::parse($punch->punched_at);
        $this->ensureUnlocked($punch->employee, $at->subDay(), $at);

        $punch->update(['voided_at' => now(), 'voided_by' => $user->id, 'void_reason' => $reason]);

        $this->processor->rebuild($punch->employee, $at->subDay(), $at);
    }

    /**
     * Record a decision on a day (status, waived late, approved overtime). Fields left out keep their value.
     *
     * @param  array{status?: string|null, waive_late?: bool, approved_overtime_minutes?: int|null, reason?: string|null}  $decision
     *
     * @throws AttendanceLockedException
     */
    public function decide(Employee $employee, CarbonImmutable $date, ?int $shiftId, array $decision, User $user): AttendanceOverride
    {
        $this->ensureUnlocked($employee, $date, $date);

        $override = AttendanceOverride::query()->firstOrNew([
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'shift_id' => $shiftId,
        ]);

        $override->fill([...$decision, 'created_by' => $user->id])->save();

        $this->processor->rebuild($employee, $date, $date);

        return $override;
    }

    /**
     * @throws AttendanceLockedException
     */
    private function ensureUnlocked(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $locked = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->between($from, $to)
            ->whereNotNull('locked_at')
            ->exists();

        if ($locked) {
            throw new AttendanceLockedException;
        }
    }
}
