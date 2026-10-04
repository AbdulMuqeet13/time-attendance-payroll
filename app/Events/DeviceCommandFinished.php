<?php

namespace App\Events;

use App\Models\DeviceCommand;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device reported the result of a command (or it was given up on). Batch owners such as
 * backups and restores listen to advance their progress.
 */
class DeviceCommandFinished
{
    use Dispatchable;

    public function __construct(public DeviceCommand $command) {}
}
