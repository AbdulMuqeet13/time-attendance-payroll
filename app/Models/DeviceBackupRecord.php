<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Data uploaded by a device while its backup collects. Holds biometric templates, so it is encrypted.
 *
 * @property int $id
 * @property int $device_backup_id
 * @property string $type
 * @property array<int, array<string, mixed>> $payload
 * @property Carbon|null $created_at
 */
#[Fillable(['device_backup_id', 'type', 'payload', 'created_at'])]
class DeviceBackupRecord extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }
}
