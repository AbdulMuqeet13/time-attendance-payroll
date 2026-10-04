<?php

namespace App\Events;

use App\Models\Device;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device uploaded table data (users, templates, logs). Collecting backups listen to keep a copy.
 */
class DeviceDataReceived
{
    use Dispatchable;

    /**
     * @param  array<int, array<string, mixed>>  $records
     */
    public function __construct(public Device $device, public string $type, public array $records) {}
}
