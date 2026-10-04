<?php

namespace App\Listeners;

use App\Events\DeviceDataReceived;
use App\Services\Biometrics\BiometricTemplateStore;

class StoreUploadedBiometrics
{
    public function __construct(private BiometricTemplateStore $store) {}

    public function handle(DeviceDataReceived $event): void
    {
        if (in_array($event->type, ['fingerprint', 'face', 'biodata'], true)) {
            $this->store->store($event->device, $event->type, $event->records);
        }
    }
}
