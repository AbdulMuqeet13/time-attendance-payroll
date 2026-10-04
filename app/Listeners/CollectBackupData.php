<?php

namespace App\Listeners;

use App\Events\DeviceDataReceived;
use App\Services\Backups\DeviceBackupService;

class CollectBackupData
{
    public function __construct(private DeviceBackupService $backups) {}

    public function handle(DeviceDataReceived $event): void
    {
        $this->backups->collect($event->device, $event->type, $event->records);
    }
}
