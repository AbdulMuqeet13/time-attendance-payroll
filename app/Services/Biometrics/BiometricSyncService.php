<?php

namespace App\Services\Biometrics;

use App\Enums\BiometricType;
use App\Enums\DeviceCommandType;
use App\Enums\EnrollmentStatus;
use App\Exceptions\Biometrics\BiometricSyncException;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceEnrollment;
use App\Models\Employee;
use App\Models\User;
use App\Services\Adms\AdmsCommandBuilder;
use App\Services\Adms\DeviceCommandQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Puts employees (and their stored biometrics) on devices, and takes them off again.
 *
 * Each push is one command batch per device; the enrollment row tracks the batch until the device
 * reports every result (see UpdateEnrollmentProgress).
 */
class BiometricSyncService
{
    public function __construct(private AdmsCommandBuilder $builder, private DeviceCommandQueue $queue) {}

    /**
     * Create the employee on the device (and copy any templates we hold), ready to scan.
     *
     * @return array{pushed_templates: int, skipped_templates: int}
     *
     * @throws BiometricSyncException
     */
    public function push(Employee $employee, Device $device, ?User $user = null): array
    {
        if ($employee->device_pin === null) {
            throw new BiometricSyncException("{$employee->name} has no device PIN. Set one on their profile first.");
        }

        if (! $device->acceptsData()) {
            throw new BiometricSyncException("{$device->name} is not claimed or is disabled.");
        }

        [$compatible, $skipped] = $employee->biometricTemplates
            ->sortBy(fn (BiometricTemplate $template) => [$template->type === BiometricType::Fingerprint ? 0 : 1, $template->finger_index])
            ->partition(fn (BiometricTemplate $template) => $this->isCompatible($template, $device));

        $commands = [
            $this->builder->updateUser($employee->device_pin, $employee->name),
            ...$compatible->map(fn (BiometricTemplate $template) => $this->templateCommand($employee->device_pin, $template))->all(),
        ];

        $batchId = (string) Str::uuid();
        $this->queue->queueMany($device, $commands, $batchId, $user);

        DeviceEnrollment::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'device_id' => $device->id],
            [
                'status' => EnrollmentStatus::Queued,
                'batch_id' => $batchId,
                'message' => $skipped->isEmpty() ? null : "{$skipped->count()} template(s) skipped: made by a different algorithm than this device uses.",
            ],
        );

        return ['pushed_templates' => $compatible->count(), 'skipped_templates' => $skipped->count()];
    }

    /**
     * Push the employee to several devices. Devices that can't take them are reported, not fatal.
     *
     * @param  Collection<int, Device>  $devices
     * @return array{devices: int, errors: array<int, string>}
     */
    public function pushToDevices(Employee $employee, Collection $devices, ?User $user = null): array
    {
        $employee->loadMissing('biometricTemplates');
        $pushed = 0;
        $errors = [];

        foreach ($devices as $device) {
            try {
                $this->push($employee, $device, $user);
                $pushed++;
            } catch (BiometricSyncException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        return ['devices' => $pushed, 'errors' => $errors];
    }

    /**
     * Put every active employee with a PIN on the device, e.g. a new or replaced device.
     *
     * @return int The number of employees queued
     */
    public function pushAllEmployees(Device $device, ?User $user = null): int
    {
        $count = 0;

        Employee::query()
            ->active()
            ->whereNotNull('device_pin')
            ->when($device->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->with('biometricTemplates')
            ->chunkById(100, function (Collection $employees) use ($device, $user, &$count) {
                foreach ($employees as $employee) {
                    $this->push($employee, $device, $user);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Delete the employee from the device. Their scans already received stay in attendance.
     */
    public function remove(Employee $employee, Device $device, ?User $user = null): void
    {
        if ($employee->device_pin === null) {
            return;
        }

        $batchId = (string) Str::uuid();
        $this->queue->queue($device, $this->builder->deleteUser($employee->device_pin), $batchId, $user);

        DeviceEnrollment::query()->updateOrCreate(
            ['employee_id' => $employee->id, 'device_id' => $device->id],
            ['status' => EnrollmentStatus::Removing, 'batch_id' => $batchId, 'message' => null],
        );
    }

    /**
     * Remove the employee from every device that holds them (e.g. when they leave).
     *
     * @return int The number of devices queued
     */
    public function removeFromAllDevices(Employee $employee, ?User $user = null): int
    {
        $enrollments = $employee->deviceEnrollments()
            ->whereIn('status', [EnrollmentStatus::OnDevice, EnrollmentStatus::Queued, EnrollmentStatus::Failed])
            ->with('device')
            ->get();

        foreach ($enrollments as $enrollment) {
            if ($enrollment->device->acceptsData()) {
                $this->remove($employee, $enrollment->device, $user);
            }
        }

        return $enrollments->count();
    }

    /**
     * Templates are tied to the algorithm that made them; a device using a different one rejects them.
     * Unknown versions on either side are allowed through (the device reports a failure if wrong).
     */
    public function isCompatible(BiometricTemplate $template, Device $device): bool
    {
        $deviceVersion = match ($template->type) {
            BiometricType::Fingerprint => $device->fp_algorithm,
            BiometricType::Face => $device->face_algorithm,
            default => null,
        };

        if ($deviceVersion === null || $template->major_version === null) {
            return true;
        }

        return (string) (int) $deviceVersion === (string) (int) $template->major_version;
    }

    /**
     * @return array{DeviceCommandType, string}
     */
    private function templateCommand(string $pin, BiometricTemplate $template): array
    {
        if ($template->storage === BiometricTemplate::STORAGE_BIODATA) {
            return $this->builder->updateBiodata(
                $pin,
                $template->biodata_type ?? 1,
                $template->type === BiometricType::Fingerprint ? $template->finger_index : 0,
                $template->type === BiometricType::Fingerprint ? 0 : $template->finger_index,
                $template->template,
                $template->major_version ?? '0',
                $template->minor_version ?? '0',
                $template->format ?? '0',
                $template->valid_flag,
            );
        }

        return $this->builder->updateFingerprint($pin, $template->finger_index, $template->template, $template->size ?? strlen($template->template), $template->valid_flag);
    }
}
