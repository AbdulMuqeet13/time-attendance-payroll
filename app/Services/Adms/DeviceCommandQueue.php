<?php

namespace App\Services\Adms;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Events\DeviceCommandFinished;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Queues commands for devices, hands them out when the device polls, and records the results.
 *
 * Unlike a plain "pending until done" queue, a command that fails is marked failed and the queue moves on.
 * A delivered command that is never acknowledged is re-sent after RETRY_AFTER_MINUTES, at most MAX_ATTEMPTS times.
 */
class DeviceCommandQueue
{
    /** Commands handed out per poll. Devices execute them in order and report each result. */
    public const BATCH_SIZE = 20;

    public const RETRY_AFTER_MINUTES = 5;

    public const MAX_ATTEMPTS = 3;

    /**
     * @param  array{DeviceCommandType, string}  $command  as returned by AdmsCommandBuilder
     */
    public function queue(Device $device, array $command, ?string $batchId = null, ?User $user = null): DeviceCommand
    {
        return $this->queueMany($device, [$command], $batchId, $user)->first();
    }

    /**
     * @param  array<int, array{DeviceCommandType, string}>  $commands
     * @return Collection<int, DeviceCommand>
     */
    public function queueMany(Device $device, array $commands, ?string $batchId = null, ?User $user = null): Collection
    {
        return DB::transaction(function () use ($device, $commands, $batchId, $user) {
            Device::query()->whereKey($device->id)->lockForUpdate()->first();
            $sequence = (int) DeviceCommand::query()->where('device_id', $device->id)->max('sequence');

            return collect($commands)->map(function (array $command) use ($device, $batchId, $user, &$sequence) {
                return DeviceCommand::create([
                    'device_id' => $device->id,
                    'batch_id' => $batchId,
                    'sequence' => ++$sequence,
                    'type' => $command[0],
                    'command' => $command[1],
                    'status' => DeviceCommandStatus::Pending,
                    'created_by' => $user?->id,
                ]);
            });
        });
    }

    /**
     * The commands to send on this poll, marked as sent. Stale sent commands are retried or given up on first.
     *
     * @return Collection<int, DeviceCommand>
     */
    public function deliver(Device $device): Collection
    {
        $this->expireStale($device);

        return DB::transaction(function () use ($device) {
            $commands = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('status', DeviceCommandStatus::Pending)
                ->orderBy('sequence')
                ->limit(self::BATCH_SIZE)
                ->lockForUpdate()
                ->get();

            foreach ($commands as $command) {
                $command->update([
                    'status' => DeviceCommandStatus::Sent,
                    'sent_at' => now(),
                    'attempts' => $command->attempts + 1,
                ]);
            }

            return $commands;
        });
    }

    /**
     * Record the results the device reported for delivered commands.
     *
     * @param  array<int, array{id: int, return: int, cmd: string|null}>  $results
     */
    public function recordResults(Device $device, array $results, string $rawBody = ''): void
    {
        foreach ($results as $result) {
            $command = DeviceCommand::query()
                ->where('device_id', $device->id)
                ->where('sequence', $result['id'])
                ->first();

            if (! $command || $command->isFinished()) {
                continue;
            }

            $command->update([
                'status' => $result['return'] === 0 ? DeviceCommandStatus::Succeeded : DeviceCommandStatus::Failed,
                'executed_at' => now(),
                'return_code' => $result['return'],
                'response' => mb_substr($rawBody, 0, 2000),
            ]);

            DeviceCommandFinished::dispatch($command);
        }
    }

    public function cancel(DeviceCommand $command): void
    {
        if ($command->isFinished()) {
            return;
        }

        $command->update(['status' => DeviceCommandStatus::Cancelled]);
        DeviceCommandFinished::dispatch($command);
    }

    /**
     * Put a failed or cancelled command back in the queue.
     */
    public function retry(DeviceCommand $command): void
    {
        $command->update([
            'status' => DeviceCommandStatus::Pending,
            'attempts' => 0,
            'sent_at' => null,
            'executed_at' => null,
            'return_code' => null,
            'response' => null,
        ]);
    }

    /**
     * Sent commands without an answer after RETRY_AFTER_MINUTES go back to pending, or fail after MAX_ATTEMPTS.
     */
    private function expireStale(Device $device): void
    {
        $stale = DeviceCommand::query()
            ->where('device_id', $device->id)
            ->where('status', DeviceCommandStatus::Sent)
            ->where('sent_at', '<', now()->subMinutes(self::RETRY_AFTER_MINUTES))
            ->get();

        foreach ($stale as $command) {
            if ($command->attempts >= self::MAX_ATTEMPTS) {
                $command->update([
                    'status' => DeviceCommandStatus::Failed,
                    'response' => 'No response from the device after '.self::MAX_ATTEMPTS.' attempts.',
                ]);
                DeviceCommandFinished::dispatch($command);
            } else {
                $command->update(['status' => DeviceCommandStatus::Pending]);
            }
        }
    }
}
