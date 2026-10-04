<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum DeviceCommandType: string
{
    use HasOptions;

    case UserUpdate = 'user_update';
    case UserDelete = 'user_delete';
    case FingerprintUpdate = 'fingerprint_update';
    case BiodataUpdate = 'biodata_update';
    case QueryUsers = 'query_users';
    case QueryTemplates = 'query_templates';
    case QueryAttendance = 'query_attendance';
    case ClearAttendance = 'clear_attendance';
    case ClearAllData = 'clear_all_data';
    case Reboot = 'reboot';
    case Info = 'info';
    case Check = 'check';
}
