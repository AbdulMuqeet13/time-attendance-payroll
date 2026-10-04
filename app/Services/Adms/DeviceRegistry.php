<?php

namespace App\Services\Adms;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * Finds devices by serial number, registering unknown ones as unclaimed, and keeps their status fresh.
 */
class DeviceRegistry
{
    public function __construct(private AdmsParser $parser) {}

    public function findOrRegister(string $serialNumber, ?string $ipAddress): Device
    {
        $device = Device::query()->firstOrCreate(
            ['serial_number' => $serialNumber],
            ['name' => "New device ({$serialNumber})", 'branch_id' => null, 'ip_address' => $ipAddress],
        );

        if ($device->wasRecentlyCreated) {
            Log::info('ADMS: registered new unclaimed device', ['sn' => $serialNumber, 'ip' => $ipAddress]);
        }

        return $device;
    }

    public function find(?string $serialNumber): ?Device
    {
        return $serialNumber ? Device::query()->where('serial_number', $serialNumber)->first() : null;
    }

    /**
     * Record that the device is alive, plus anything it told us about itself.
     */
    public function touch(Device $device, ?string $ipAddress, ?string $info = null, ?string $pushVersion = null): void
    {
        $device->fill(array_filter([
            'last_seen_at' => now(),
            'ip_address' => $ipAddress,
            'push_version' => $pushVersion,
            ...$this->parser->info($info),
        ], fn (mixed $value) => $value !== null))->save();
    }
}
