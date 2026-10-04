<?php

namespace App\Services\Adms;

use App\Enums\PunchSource;
use App\Events\PunchesRecorded;
use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use Carbon\CarbonImmutable;

/**
 * Stores scans in the raw punch log and tells the attendance engine which employee days changed.
 */
class PunchRecorder
{
    /**
     * Store device scans. Scans already stored (device re-push, restored backup) are skipped.
     *
     * @param  array<int, array{pin: string, punched_at: string, state: int|null, verify: int|null, work_code: string|null}>  $logs
     * @return int The number of new punches
     */
    public function recordDeviceLogs(Device $device, array $logs, PunchSource $source = PunchSource::Device): int
    {
        return $this->recordLogs($device->serial_number, $device->id, $logs, $source);
    }

    /**
     * Store scans for a device that may no longer exist (e.g. imported from the backup of a replaced unit).
     *
     * @param  array<int, array{pin: string, punched_at: string, state: int|null, verify: int|null, work_code: string|null}>  $logs
     * @return int The number of new punches
     */
    public function recordLogs(string $serialNumber, ?int $deviceId, array $logs, PunchSource $source): int
    {
        if ($logs === []) {
            return 0;
        }

        $employeeIds = Employee::withTrashed()
            ->whereIn('device_pin', array_unique(array_column($logs, 'pin')))
            ->pluck('id', 'device_pin');

        $now = now();
        $rows = array_map(fn (array $log) => [
            'device_id' => $deviceId,
            'employee_id' => $employeeIds[$log['pin']] ?? null,
            'pin' => $log['pin'],
            'punched_at' => $log['punched_at'],
            'punch_state' => $log['state'],
            'verify_type' => $log['verify'],
            'work_code' => $log['work_code'],
            'source' => $source->value,
            'dedupe_hash' => AttendancePunch::hashFor($serialNumber, $log['pin'], $log['punched_at']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $logs);

        $hashes = array_column($rows, 'dedupe_hash');
        $existing = AttendancePunch::query()->whereIn('dedupe_hash', $hashes)->pluck('dedupe_hash')->flip();
        $new = array_values(array_filter($rows, fn (array $row) => ! isset($existing[$row['dedupe_hash']])));

        foreach (array_chunk($new, 500) as $chunk) {
            AttendancePunch::query()->insertOrIgnore($chunk);
        }

        $this->announce($new);

        return count($new);
    }

    /**
     * Attach earlier scans with this employee's PIN that matched nobody at the time.
     *
     * @return int The number of punches linked
     */
    public function linkUnmatchedPunches(Employee $employee): int
    {
        if ($employee->device_pin === null) {
            return 0;
        }

        $punches = AttendancePunch::query()
            ->unmatched()
            ->where('pin', $employee->device_pin)
            ->get(['id', 'punched_at']);

        if ($punches->isEmpty()) {
            return 0;
        }

        AttendancePunch::query()->whereKey($punches->modelKeys())->update(['employee_id' => $employee->id]);

        $this->announce($punches->map(fn (AttendancePunch $punch) => [
            'employee_id' => $employee->id,
            'punched_at' => $punch->punched_at->toDateTimeString(),
        ])->all());

        return $punches->count();
    }

    /**
     * @param  array<int, array{employee_id: int|null, punched_at: string}>  $rows
     */
    private function announce(array $rows): void
    {
        $employeeDates = [];

        foreach ($rows as $row) {
            if ($row['employee_id'] === null) {
                continue;
            }

            $date = CarbonImmutable::parse($row['punched_at'])->toDateString();
            $employeeDates[$row['employee_id']][$date] = $date;
        }

        if ($employeeDates !== []) {
            PunchesRecorded::dispatch(array_map('array_values', $employeeDates));
        }
    }
}
