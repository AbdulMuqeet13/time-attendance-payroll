<?php

namespace App\Services\Adms;

use App\Enums\DeviceCommandType;
use App\Models\Device;
use App\Models\User;
use App\Services\Biometrics\BiometricSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Operator actions on a device, turned into queued ADMS commands.
 */
class DeviceActions
{
    public function __construct(
        private AdmsCommandBuilder $builder,
        private DeviceCommandQueue $queue,
        private BiometricSyncService $biometricSync,
    ) {}

    /**
     * @param  array{from?: string|null, to?: string|null}  $options  from/to are required for pull-logs (validated by DeviceActionRequest)
     * @return string A message describing what was queued
     */
    public function run(Device $device, string $action, array $options, User $user): string
    {
        return match ($action) {
            'reboot' => $this->queueOne($device, $this->builder->reboot(), $user, 'Reboot queued.'),
            'refresh-info' => $this->queueOne($device, $this->builder->info(), $user, 'The device will report its counts on the next poll.'),
            'pull-users' => $this->pullUsers($device, $user),
            'pull-logs' => $this->queueOne(
                $device,
                $this->builder->queryAttendance(CarbonImmutable::parse($options['from'] ?? 'today')->startOfDay(), CarbonImmutable::parse($options['to'] ?? 'today')->endOfDay()),
                $user,
                'The device will upload attendance for those dates. Scans already stored are skipped.',
            ),
            'push-all-employees' => 'Queued '.$this->biometricSync->pushAllEmployees($device, $user).' employees with their stored biometrics.',
            'clear-logs' => $this->queueOne($device, $this->builder->clearAttendance(), $user, 'The device will delete its attendance log. Scans already received stay in the app.'),
            default => throw new InvalidArgumentException("Unknown device action [{$action}]."),
        };
    }

    /**
     * Ask the device to upload every user and biometric template.
     *
     * Older firmware has no table query: resetting the OPERLOG stamp makes it re-send its whole
     * operation log (users and fingerprints) after CHECK.
     */
    private function pullUsers(Device $device, User $user): string
    {
        if (! $device->usesPushV3()) {
            $device->update(['last_operlog_stamp' => '0']);
        }

        $this->queue->queueMany(
            $device,
            [$this->builder->queryUsers($device), ...$this->builder->queryTemplates($device)],
            (string) Str::uuid(),
            $user,
        );

        return 'The device will upload its users and biometrics on the next poll.';
    }

    /**
     * @param  array{DeviceCommandType, string}  $command
     */
    private function queueOne(Device $device, array $command, User $user, string $message): string
    {
        $this->queue->queue($device, $command, null, $user);

        return $message;
    }
}
