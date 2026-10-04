<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum BackupMode: string
{
    use HasOptions;

    case DeviceQuery = 'device_query';
    case ServerSnapshot = 'server_snapshot';
    case Uploaded = 'uploaded';
}
