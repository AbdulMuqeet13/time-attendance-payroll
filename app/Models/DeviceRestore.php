<?php

namespace App\Models;

use App\Enums\RestoreStatus;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $device_backup_id null = from the app's current employees and templates
 * @property int $target_device_id
 * @property bool $include_users
 * @property bool $include_templates
 * @property bool $clear_first
 * @property RestoreStatus $status
 * @property string $batch_id
 * @property int $users_count
 * @property int $templates_count
 * @property int $skipped_templates
 * @property int $total_commands
 * @property int $succeeded_commands
 * @property int $failed_commands
 * @property int|null $created_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property-read DeviceBackup|null $backup
 * @property-read Device $targetDevice
 * @property-read User|null $creator
 */
#[Fillable([
    'device_backup_id', 'target_device_id', 'include_users', 'include_templates', 'clear_first', 'status', 'batch_id',
    'users_count', 'templates_count', 'skipped_templates', 'total_commands', 'succeeded_commands', 'failed_commands',
    'created_by', 'completed_at',
])]
class DeviceRestore extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RestoreStatus::class,
            'include_users' => 'boolean',
            'include_templates' => 'boolean',
            'clear_first' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DeviceBackup, $this>
     */
    public function backup(): BelongsTo
    {
        return $this->belongsTo(DeviceBackup::class, 'device_backup_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
