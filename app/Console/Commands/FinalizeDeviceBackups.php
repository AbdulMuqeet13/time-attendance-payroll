<?php

namespace App\Console\Commands;

use App\Services\Backups\DeviceBackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('devices:finalize-backups')]
#[Description('Write the files of device backups whose devices have finished uploading')]
class FinalizeDeviceBackups extends Command
{
    public function handle(DeviceBackupService $backups): int
    {
        $this->info("Finished {$backups->finalizeDue()} backup(s).");

        return self::SUCCESS;
    }
}
