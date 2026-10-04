<?php

namespace App\Console\Commands;

use App\Exceptions\Backups\BackupException;
use App\Models\Device;
use App\Services\Backups\DeviceBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('devices:auto-backup')]
#[Description('Start the scheduled backups (daily, or weekly on Sundays) and remove backups beyond each device\'s retention')]
class RunAutomaticDeviceBackups extends Command
{
    public function handle(DeviceBackupService $backups): int
    {
        $devices = Device::query()->claimed()->where('is_active', true)
            ->where(fn ($query) => $query->where('auto_backup', 'daily')
                ->when(now()->isSunday(), fn ($query) => $query->orWhere('auto_backup', 'weekly')))
            ->get();

        foreach ($devices as $device) {
            try {
                $backups->startFromDevice($device, ['users' => true, 'templates' => true, 'logs' => true, 'logs_from' => now()->subDays(35)->toDateString(), 'logs_to' => now()->toDateString()]);
                $this->line("Started backup of {$device->name}.");
            } catch (BackupException $exception) {
                $this->warn("{$device->name}: {$exception->getMessage()}");
            }

            $backups->prune($device);
        }

        return self::SUCCESS;
    }
}
