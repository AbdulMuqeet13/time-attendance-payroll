<?php

namespace App\Listeners;

use App\Enums\RestoreStatus;
use App\Events\DeviceCommandFinished;
use App\Models\DeviceRestore;
use App\Services\Backups\DeviceRestoreService;

class TrackRestoreProgress
{
    public function __construct(private DeviceRestoreService $restores) {}

    public function handle(DeviceCommandFinished $event): void
    {
        if ($event->command->batch_id === null) {
            return;
        }

        $restore = DeviceRestore::query()
            ->where('batch_id', $event->command->batch_id)
            ->where('status', RestoreStatus::Running)
            ->first();

        if ($restore) {
            $this->restores->refreshProgress($restore);
        }
    }
}
