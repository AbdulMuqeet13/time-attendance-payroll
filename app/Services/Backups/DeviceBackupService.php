<?php

namespace App\Services\Backups;

use App\Enums\BackupMode;
use App\Enums\BackupStatus;
use App\Enums\BiometricType;
use App\Enums\EnrollmentStatus;
use App\Enums\PunchSource;
use App\Exceptions\Backups\BackupException;
use App\Models\AttendancePunch;
use App\Models\BiometricTemplate;
use App\Models\Device;
use App\Models\DeviceBackup;
use App\Models\DeviceCommand;
use App\Models\DeviceEnrollment;
use App\Models\DeviceUser;
use App\Models\Employee;
use App\Models\User;
use App\Services\Adms\AdmsCommandBuilder;
use App\Services\Adms\DeviceCommandQueue;
use App\Services\Adms\PunchRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Device backups.
 *
 *  - From the device: queries its users, templates and logs; what it uploads is collected and, once the
 *    commands are answered and the device has been quiet for QUIET_MINUTES, written to an encrypted file.
 *    After TIMEOUT_MINUTES whatever arrived is kept as a partial backup.
 *  - From the server: built instantly from what the app already holds for that device (works when the
 *    device is dead).
 *  - Uploaded: a backup file from this system, e.g. kept offline.
 */
class DeviceBackupService
{
    public const QUIET_MINUTES = 3;

    public const TIMEOUT_MINUTES = 30;

    public function __construct(
        private AdmsCommandBuilder $builder,
        private DeviceCommandQueue $queue,
        private BackupFile $file,
        private PunchRecorder $punchRecorder,
    ) {}

    /**
     * @param  array{users?: bool, templates?: bool, logs?: bool, logs_from?: string|null, logs_to?: string|null}  $includes
     *
     * @throws BackupException
     */
    public function startFromDevice(Device $device, array $includes, ?User $user = null): DeviceBackup
    {
        if (! $device->acceptsData()) {
            throw new BackupException('The device must be claimed and enabled to be backed up.');
        }

        if (DeviceBackup::query()->where('device_id', $device->id)->where('status', BackupStatus::Collecting)->exists()) {
            throw new BackupException('A backup of this device is already in progress.');
        }

        $backup = $this->newBackup($device, BackupMode::DeviceQuery, $includes, $user, BackupStatus::Collecting);
        $commands = [];

        if ($backup->include_users || $backup->include_templates) {
            if (! $device->usesPushV3()) {
                $device->update(['last_operlog_stamp' => '0']);
            }

            $commands[] = $this->builder->queryUsers($device);
            array_push($commands, ...$this->builder->queryTemplates($device));
        }

        if ($backup->include_logs) {
            $commands[] = $this->builder->queryAttendance(
                CarbonImmutable::parse($backup->logs_from->toDateString())->startOfDay(),
                CarbonImmutable::parse($backup->logs_to->toDateString())->endOfDay(),
            );
        }

        $this->queue->queueMany($device, $commands, $backup->batch_id, $user);

        return $backup;
    }

    /**
     * @param  array{users?: bool, templates?: bool, logs?: bool, logs_from?: string|null, logs_to?: string|null}  $includes
     */
    public function snapshotFromServer(Device $device, array $includes, ?User $user = null): DeviceBackup
    {
        $backup = $this->newBackup($device, BackupMode::ServerSnapshot, $includes, $user, BackupStatus::Collecting);

        $employees = Employee::withTrashed()
            ->whereNotNull('device_pin')
            ->whereIn('id', DeviceEnrollment::query()->where('device_id', $device->id)
                ->whereIn('status', [EnrollmentStatus::OnDevice, EnrollmentStatus::Queued])->select('employee_id'))
            ->with('biometricTemplates')
            ->get();

        $users = $employees->map(fn (Employee $employee) => ['pin' => $employee->device_pin, 'name' => $employee->name, 'privilege' => 0, 'card' => null]);
        $knownPins = $users->pluck('pin')->flip();
        $users = $users->concat(DeviceUser::query()->where('device_id', $device->id)->get()
            ->reject(fn (DeviceUser $deviceUser) => $knownPins->has($deviceUser->pin))
            ->map(fn (DeviceUser $deviceUser) => ['pin' => $deviceUser->pin, 'name' => $deviceUser->name, 'privilege' => $deviceUser->privilege, 'card' => $deviceUser->card]));

        $templates = $employees->flatMap(fn (Employee $employee) => $employee->biometricTemplates
            ->map(fn (BiometricTemplate $template) => $this->templateEntry($employee->device_pin, $template)));

        $logs = $backup->include_logs
            ? AttendancePunch::query()
                ->where('device_id', $device->id)
                ->whereBetween('punched_at', [$backup->logs_from->startOfDay(), $backup->logs_to->endOfDay()])
                ->orderBy('punched_at')
                ->get()
                ->map(fn (AttendancePunch $punch) => [
                    'pin' => $punch->pin,
                    'punched_at' => $punch->punched_at->toDateTimeString(),
                    'state' => $punch->punch_state,
                    'verify' => $punch->verify_type,
                    'work_code' => $punch->work_code,
                ])
            : collect();

        $this->writeFile($backup, $device, $backup->include_users ? $users : collect(), $backup->include_templates ? $templates : collect(), $logs, BackupStatus::Completed);

        return $backup->fresh();
    }

    /**
     * Keep a copy of data uploaded by a device whose backup is collecting.
     *
     * @param  array<int, array<string, mixed>>  $records
     */
    public function collect(Device $device, string $type, array $records): void
    {
        $backup = DeviceBackup::query()
            ->where('device_id', $device->id)
            ->where('status', BackupStatus::Collecting)
            ->where('mode', BackupMode::DeviceQuery)
            ->latest('id')
            ->first();

        if (! $backup || $records === []) {
            return;
        }

        $wanted = match ($type) {
            'user' => $backup->include_users,
            'fingerprint', 'face', 'biodata' => $backup->include_templates,
            'attlog' => $backup->include_logs,
            default => false,
        };

        if ($wanted) {
            $backup->records()->create(['type' => $type, 'payload' => $records, 'created_at' => now()]);
        }

        $backup->update(['last_activity_at' => now()]);
    }

    /**
     * Finish collecting backups whose commands are answered and whose device has gone quiet (or that timed out).
     *
     * @return int The number of backups finished
     */
    public function finalizeDue(): int
    {
        $finished = 0;

        DeviceBackup::query()->where('status', BackupStatus::Collecting)->where('mode', BackupMode::DeviceQuery)->with('device')->each(function (DeviceBackup $backup) use (&$finished) {
            $commandsOpen = DeviceCommand::query()->where('batch_id', $backup->batch_id)->open()->exists();
            $lastActivity = $backup->last_activity_at ?? $backup->started_at;
            $quiet = $lastActivity->lt(now()->subMinutes(self::QUIET_MINUTES));
            $timedOut = $backup->started_at->lt(now()->subMinutes(self::TIMEOUT_MINUTES));

            if ((! $commandsOpen && $quiet) || $timedOut) {
                $this->finalize($backup, $commandsOpen || $timedOut);
                $finished++;
            }
        });

        return $finished;
    }

    /**
     * Fold the collected records into the backup file.
     */
    public function finalize(DeviceBackup $backup, bool $incomplete = false): void
    {
        $users = collect();
        $templates = collect();
        $logs = collect();

        foreach ($backup->records()->orderBy('id')->cursor() as $record) {
            foreach ($record->payload as $fields) {
                match ($record->type) {
                    'user' => $users->push($this->userEntry($fields)),
                    'fingerprint', 'face', 'biodata' => $templates->push($this->uploadedTemplateEntry($record->type, $fields, $backup)),
                    'attlog' => $logs->push($fields),
                    default => null,
                };
            }
        }

        $failedCommands = DeviceCommand::query()->where('batch_id', $backup->batch_id)->where('status', 'failed')->count();
        $empty = $users->isEmpty() && $templates->isEmpty() && $logs->isEmpty();
        $status = match (true) {
            $empty => BackupStatus::Failed,
            $incomplete || $failedCommands > 0 => BackupStatus::Partial,
            default => BackupStatus::Completed,
        };

        $this->writeFile(
            $backup,
            $backup->device,
            $users->filter(fn ($user) => $user['pin'] !== '')->keyBy('pin')->values(),
            $templates->filter()->keyBy(fn (array $template) => "{$template['pin']}|{$template['type']}|{$template['index']}")->values(),
            $logs->unique(fn (array $log) => "{$log['pin']}|{$log['punched_at']}")->sortBy('punched_at')->values(),
            $status,
            $empty ? 'The device sent nothing. Check that it is online and see its command log.' : null,
        );

        $backup->records()->delete();
    }

    /**
     * Store a backup file uploaded by a user.
     *
     * @throws BackupException
     */
    public function upload(string $encryptedContents, ?Device $device, User $user): DeviceBackup
    {
        $contents = $this->file->decode($encryptedContents);
        $source = $contents['device'] ?? [];

        $backup = DeviceBackup::query()->create([
            'device_id' => $device?->id,
            'device_serial' => (string) ($source['serial'] ?? 'unknown'),
            'device_name' => (string) ($source['name'] ?? 'Uploaded backup'),
            'mode' => BackupMode::Uploaded,
            'include_users' => ($contents['users'] ?? []) !== [],
            'include_templates' => ($contents['templates'] ?? []) !== [],
            'include_logs' => ($contents['logs'] ?? []) !== [],
            'status' => BackupStatus::Completed,
            'started_at' => now(),
            'completed_at' => now(),
            'fp_algorithm' => $source['fp_algorithm'] ?? null,
            'face_algorithm' => $source['face_algorithm'] ?? null,
            'created_by' => $user->id,
        ]);

        $written = $this->file->write(collect($contents)->except(['format', 'version', '_checksum'])->all(), $this->fileName($backup));
        $backup->update([
            'file_path' => $written['path'],
            'file_size' => $written['size'],
            'checksum' => $written['checksum'],
            'counts' => $this->counts(collect($contents['users'] ?? []), collect($contents['templates'] ?? []), collect($contents['logs'] ?? [])),
        ]);

        return $backup;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws BackupException
     */
    public function contents(DeviceBackup $backup): array
    {
        if (! $backup->hasFile()) {
            throw new BackupException('This backup has no file yet.');
        }

        return $this->file->read($backup->file_path, $backup->checksum);
    }

    /**
     * Add the backup's attendance logs to the app (skipping scans already stored) and recalculate attendance.
     *
     * @return int The number of new punches
     *
     * @throws BackupException
     */
    public function importLogs(DeviceBackup $backup): int
    {
        $logs = array_map(fn (array $log) => [
            'pin' => strtoupper((string) $log['pin']),
            'punched_at' => (string) $log['punched_at'],
            'state' => $log['state'] ?? null,
            'verify' => $log['verify'] ?? null,
            'work_code' => $log['work_code'] ?? null,
        ], $this->contents($backup)['logs'] ?? []);

        $deviceId = $backup->device_id ?? Device::query()->where('serial_number', $backup->device_serial)->value('id');

        return $this->punchRecorder->recordLogs($backup->device_serial, $deviceId, $logs, PunchSource::Restore);
    }

    public function delete(DeviceBackup $backup): void
    {
        $this->file->delete($backup->file_path);
        $backup->delete();
    }

    /**
     * Keep only the device's newest completed backups, as set on the device.
     */
    public function prune(Device $device): int
    {
        $old = DeviceBackup::query()
            ->where('device_id', $device->id)
            ->whereIn('status', [BackupStatus::Completed, BackupStatus::Partial, BackupStatus::Failed])
            ->where('mode', '!=', BackupMode::Uploaded)
            ->latest('id')
            ->skip($device->backup_retention)
            ->take(PHP_INT_MAX)
            ->get();

        $old->each(fn (DeviceBackup $backup) => $this->delete($backup));

        return $old->count();
    }

    /**
     * @param  array{users?: bool, templates?: bool, logs?: bool, logs_from?: string|null, logs_to?: string|null}  $includes
     */
    private function newBackup(Device $device, BackupMode $mode, array $includes, ?User $user, BackupStatus $status): DeviceBackup
    {
        $includeLogs = (bool) ($includes['logs'] ?? false);

        return DeviceBackup::query()->create([
            'device_id' => $device->id,
            'device_serial' => $device->serial_number,
            'device_name' => $device->name,
            'mode' => $mode,
            'include_users' => (bool) ($includes['users'] ?? true),
            'include_templates' => (bool) ($includes['templates'] ?? true),
            'include_logs' => $includeLogs,
            'logs_from' => $includeLogs ? ($includes['logs_from'] ?? now()->subDays(30)->toDateString()) : null,
            'logs_to' => $includeLogs ? ($includes['logs_to'] ?? now()->toDateString()) : null,
            'status' => $status,
            'batch_id' => (string) Str::uuid(),
            'started_at' => now(),
            'fp_algorithm' => $device->fp_algorithm,
            'face_algorithm' => $device->face_algorithm,
            'created_by' => $user?->id,
        ]);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $users
     * @param  Collection<int, array<string, mixed>>  $templates
     * @param  Collection<int, array<string, mixed>>  $logs
     */
    private function writeFile(DeviceBackup $backup, ?Device $device, Collection $users, Collection $templates, Collection $logs, BackupStatus $status, ?string $error = null): void
    {
        $written = $this->file->write([
            'created_at' => now()->toDateTimeString(),
            'device' => [
                'serial' => $backup->device_serial,
                'name' => $backup->device_name,
                'model' => $device?->model,
                'firmware' => $device?->firmware,
                'push_version' => $device?->push_version,
                'fp_algorithm' => $backup->fp_algorithm,
                'face_algorithm' => $backup->face_algorithm,
            ],
            'users' => $users->values()->all(),
            'templates' => $templates->values()->all(),
            'logs' => $logs->values()->all(),
        ], $this->fileName($backup));

        $backup->update([
            'status' => $status,
            'completed_at' => now(),
            'file_path' => $written['path'],
            'file_size' => $written['size'],
            'checksum' => $written['checksum'],
            'counts' => $this->counts($users, $templates, $logs),
            'error' => $error,
        ]);
    }

    /**
     * @param  Collection<int, mixed>  $users
     * @param  Collection<int, mixed>  $templates
     * @param  Collection<int, mixed>  $logs
     * @return array{users: int, fingerprints: int, faces: int, other_templates: int, logs: int}
     */
    private function counts(Collection $users, Collection $templates, Collection $logs): array
    {
        $byType = $templates->countBy('type');

        return [
            'users' => $users->count(),
            'fingerprints' => $byType['fingerprint'] ?? 0,
            'faces' => $byType['face'] ?? 0,
            'other_templates' => $templates->count() - ($byType['fingerprint'] ?? 0) - ($byType['face'] ?? 0),
            'logs' => $logs->count(),
        ];
    }

    private function fileName(DeviceBackup $backup): string
    {
        return Str::slug($backup->device_serial).'-'.$backup->id.'-'.now()->format('Ymd-His');
    }

    /**
     * @param  array<string, string>  $fields
     * @return array{pin: string, name: string|null, privilege: int, card: string|null}
     */
    private function userEntry(array $fields): array
    {
        return [
            'pin' => strtoupper(trim($fields['pin'] ?? '')),
            'name' => ($fields['name'] ?? '') !== '' ? $fields['name'] : null,
            'privilege' => (int) ($fields['pri'] ?? $fields['privilege'] ?? 0),
            'card' => ($fields['card'] ?? $fields['cardno'] ?? '') ?: null,
        ];
    }

    /**
     * @param  array<string, string>  $fields
     * @return array<string, mixed>|null
     */
    private function uploadedTemplateEntry(string $recordType, array $fields, DeviceBackup $backup): ?array
    {
        $pin = strtoupper(trim($fields['pin'] ?? ''));
        $template = $fields['tmp'] ?? $fields['template'] ?? '';

        if ($pin === '' || $template === '') {
            return null;
        }

        if ($recordType === 'biodata') {
            $type = BiometricType::fromBiodataType((int) ($fields['type'] ?? 0));

            return [
                'pin' => $pin,
                'type' => $type->value,
                'storage' => BiometricTemplate::STORAGE_BIODATA,
                'index' => $type === BiometricType::Fingerprint ? (int) ($fields['no'] ?? 0) : (int) ($fields['index'] ?? 0),
                'valid' => $fields['valid'] ?? '1',
                'size' => strlen($template),
                'biodata_type' => (int) ($fields['type'] ?? 0),
                'major_version' => $fields['majorver'] ?? null,
                'minor_version' => $fields['minorver'] ?? null,
                'format' => $fields['format'] ?? null,
                'template' => $template,
            ];
        }

        $isFace = $recordType === 'face';

        return [
            'pin' => $pin,
            'type' => $isFace ? 'face' : 'fingerprint',
            'storage' => BiometricTemplate::STORAGE_FINGERTMP,
            'index' => (int) ($fields['fid'] ?? $fields['fingerid'] ?? 0),
            'valid' => $fields['valid'] ?? '1',
            'size' => (int) ($fields['size'] ?? strlen($template)),
            'biodata_type' => null,
            'major_version' => $isFace ? $backup->face_algorithm : $backup->fp_algorithm,
            'minor_version' => null,
            'format' => null,
            'template' => $template,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateEntry(string $pin, BiometricTemplate $template): array
    {
        return [
            'pin' => $pin,
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
        ];
    }
}
