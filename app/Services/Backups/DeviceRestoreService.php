<?php

namespace App\Services\Backups;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Enums\RestoreStatus;
use App\Exceptions\Backups\BackupException;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceBackup;
use App\Models\DeviceCommand;
use App\Models\DeviceRestore;
use App\Models\Employee;
use App\Models\User;
use App\Services\Adms\AdmsCommandBuilder;
use App\Services\Adms\DeviceCommandQueue;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Puts users and biometric templates back onto a device: from a backup file, or from the app's current
 * employees and stored templates (e.g. a replacement device). Attendance logs cannot be written to a device
 * over ADMS; they are imported into the app instead (DeviceBackupService::importLogs).
 */
class DeviceRestoreService
{
    public function __construct(
        private AdmsCommandBuilder $builder,
        private DeviceCommandQueue $queue,
        private DeviceBackupService $backups,
    ) {}

    /**
     * @param  array{users?: bool, templates?: bool, clear_first?: bool}  $options
     *
     * @throws BackupException
     */
    public function start(?DeviceBackup $backup, Device $target, array $options, User $user): DeviceRestore
    {
        if (! $target->acceptsData()) {
            throw new BackupException('The target device must be claimed and enabled.');
        }

        $includeUsers = (bool) ($options['users'] ?? true);
        $includeTemplates = (bool) ($options['templates'] ?? true);
        $clearFirst = (bool) ($options['clear_first'] ?? false);

        [$users, $templates] = $backup ? $this->fromBackup($backup) : $this->fromApp($target);

        if (! $includeUsers) {
            $users = collect();
        }

        $templates = $includeTemplates ? $templates : collect();
        [$compatible, $skipped] = $templates->partition(fn (array $template) => $this->isCompatible($template, $target));

        $commands = [];

        if ($clearFirst) {
            $commands[] = $this->builder->clearAllData();
        }

        $templatesByPin = $compatible->groupBy('pin');

        foreach ($users as $deviceUser) {
            $commands[] = $this->builder->updateUser($deviceUser['pin'], (string) ($deviceUser['name'] ?? $deviceUser['pin']), (int) ($deviceUser['privilege'] ?? 0), $deviceUser['card'] ?? null);

            foreach ($templatesByPin->get($deviceUser['pin'], collect()) as $template) {
                $commands[] = $this->templateCommand($template);
            }
        }

        if ($users->isEmpty()) {
            foreach ($compatible as $template) {
                $commands[] = $this->templateCommand($template);
            }
        }

        if ($commands === []) {
            throw new BackupException('There is nothing to restore with these options.');
        }

        $restore = DeviceRestore::query()->create([
            'device_backup_id' => $backup?->id,
            'target_device_id' => $target->id,
            'include_users' => $includeUsers,
            'include_templates' => $includeTemplates,
            'clear_first' => $clearFirst,
            'status' => RestoreStatus::Running,
            'batch_id' => (string) Str::uuid(),
            'users_count' => $users->count(),
            'templates_count' => $compatible->count(),
            'skipped_templates' => $skipped->count(),
            'total_commands' => count($commands),
            'created_by' => $user->id,
        ]);

        foreach (array_chunk($commands, 500) as $chunk) {
            $this->queue->queueMany($target, $chunk, $restore->batch_id, $user);
        }

        return $restore;
    }

    /**
     * Update a running restore from its command results.
     */
    public function refreshProgress(DeviceRestore $restore): void
    {
        $statuses = DeviceCommand::query()->where('batch_id', $restore->batch_id)->pluck('status');
        $succeeded = $statuses->filter(fn (DeviceCommandStatus $status) => $status === DeviceCommandStatus::Succeeded)->count();
        $failed = $statuses->filter(fn (DeviceCommandStatus $status) => in_array($status, [DeviceCommandStatus::Failed, DeviceCommandStatus::Cancelled], true))->count();
        $done = $succeeded + $failed >= $restore->total_commands;

        $restore->update([
            'succeeded_commands' => $succeeded,
            'failed_commands' => $failed,
            'status' => ! $done ? RestoreStatus::Running : match (true) {
                $failed === 0 => RestoreStatus::Completed,
                $succeeded === 0 => RestoreStatus::Failed,
                default => RestoreStatus::Partial,
            },
            'completed_at' => $done ? now() : null,
        ]);
    }

    /**
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     *
     * @throws BackupException
     */
    private function fromBackup(DeviceBackup $backup): array
    {
        $contents = $this->backups->contents($backup);

        return [collect($contents['users'] ?? []), collect($contents['templates'] ?? [])];
    }

    /**
     * Every active employee of the device's branch with a device PIN, and their stored templates.
     *
     * @return array{0: Collection<int, array<string, mixed>>, 1: Collection<int, array<string, mixed>>}
     */
    private function fromApp(Device $target): array
    {
        $employees = Employee::query()
            ->active()
            ->whereNotNull('device_pin')
            ->where('branch_id', $target->branch_id)
            ->with('biometricTemplates')
            ->get();

        return [
            $employees->map(fn (Employee $employee) => ['pin' => $employee->device_pin, 'name' => $employee->name, 'privilege' => 0, 'card' => null])->values(),
            $employees->flatMap(fn (Employee $employee) => $employee->biometricTemplates->map(fn (BiometricTemplate $template) => [
                'pin' => $employee->device_pin,
                'type' => $template->type->value,
                'storage' => $template->storage,
                'index' => $template->finger_index,
                'valid' => $template->valid_flag,
                'size' => $template->size,
                'biodata_type' => $template->biodata_type,
                'major_version' => $template->major_version,
                'minor_version' => $template->minor_version,
                'format' => $template->format,
                'template' => $template->template,
            ]))->values(),
        ];
    }

    /**
     * @param  array<string, mixed>  $template
     */
    private function isCompatible(array $template, Device $target): bool
    {
        $deviceVersion = match ($template['type']) {
            'fingerprint' => $target->fp_algorithm,
            'face' => $target->face_algorithm,
            default => null,
        };

        if ($deviceVersion === null || ($template['major_version'] ?? null) === null) {
            return true;
        }

        return (int) $deviceVersion === (int) $template['major_version'];
    }

    /**
     * @param  array<string, mixed>  $template
     * @return array{DeviceCommandType, string}
     */
    private function templateCommand(array $template): array
    {
        if ($template['storage'] === BiometricTemplate::STORAGE_BIODATA) {
            $isFingerprint = $template['type'] === 'fingerprint';

            return $this->builder->updateBiodata(
                (string) $template['pin'],
                (int) ($template['biodata_type'] ?? 1),
                $isFingerprint ? (int) $template['index'] : 0,
                $isFingerprint ? 0 : (int) $template['index'],
                (string) $template['template'],
                (string) ($template['major_version'] ?? '0'),
                (string) ($template['minor_version'] ?? '0'),
                (string) ($template['format'] ?? '0'),
                (string) ($template['valid'] ?? '1'),
            );
        }

        return $this->builder->updateFingerprint(
            (string) $template['pin'],
            (int) $template['index'],
            (string) $template['template'],
            (int) ($template['size'] ?? strlen((string) $template['template'])),
            (string) ($template['valid'] ?? '1'),
        );
    }
}
