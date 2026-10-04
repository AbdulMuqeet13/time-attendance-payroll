<?php

namespace App\Listeners;

use App\Enums\DeviceCommandStatus;
use App\Enums\EnrollmentStatus;
use App\Events\DeviceCommandFinished;
use App\Models\DeviceCommand;
use App\Models\DeviceEnrollment;

/**
 * When every command of an enrollment's batch has a result, settle the enrollment status.
 */
class UpdateEnrollmentProgress
{
    public function handle(DeviceCommandFinished $event): void
    {
        $batchId = $event->command->batch_id;

        if ($batchId === null) {
            return;
        }

        $enrollment = DeviceEnrollment::query()->where('batch_id', $batchId)->first();

        if (! $enrollment || DeviceCommand::query()->where('batch_id', $batchId)->open()->exists()) {
            return;
        }

        $failed = DeviceCommand::query()->where('batch_id', $batchId)
            ->whereIn('status', [DeviceCommandStatus::Failed, DeviceCommandStatus::Cancelled])
            ->count();

        if ($enrollment->status === EnrollmentStatus::Removing) {
            $enrollment->update($failed > 0
                ? ['status' => EnrollmentStatus::Failed, 'message' => 'The device did not confirm the removal.']
                : ['status' => EnrollmentStatus::Removed, 'synced_at' => now(), 'message' => null]);

            return;
        }

        $enrollment->update($failed > 0
            ? ['status' => EnrollmentStatus::Failed, 'message' => "{$failed} command(s) failed on the device. Check the device command log."]
            : ['status' => EnrollmentStatus::OnDevice, 'synced_at' => now()]);
    }
}
