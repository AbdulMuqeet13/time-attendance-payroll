<?php

namespace App\Models;

use App\Enums\BackupMode;
use App\Enums\BackupStatus;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\DeviceBackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $device_id
 * @property string $device_serial
 * @property string $device_name
 * @property BackupMode $mode
 * @property bool $include_users
 * @property bool $include_templates
 * @property bool $include_logs
 * @property Carbon|null $logs_from
 * @property Carbon|null $logs_to
 * @property BackupStatus $status
 * @property string|null $batch_id
 * @property Carbon $started_at
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $completed_at
 * @property array{users?: int, fingerprints?: int, faces?: int, other_templates?: int, logs?: int}|null $counts
 * @property string|null $file_path
 * @property int|null $file_size
 * @property string|null $checksum
 * @property string|null $fp_algorithm
 * @property string|null $face_algorithm
 * @property string|null $error
 * @property int|null $created_by
 * @property-read Device|null $device
 * @property-read User|null $creator
 */
#[Fillable([
    'device_id', 'device_serial', 'device_name', 'mode', 'include_users', 'include_templates', 'include_logs',
    'logs_from', 'logs_to', 'status', 'batch_id', 'started_at', 'last_activity_at', 'completed_at', 'counts',
    'file_path', 'file_size', 'checksum', 'fp_algorithm', 'face_algorithm', 'error', 'created_by',
])]
#[Hidden(['file_path'])]
class DeviceBackup extends Model
{
    /** @use HasFactory<DeviceBackupFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => BackupMode::class,
            'status' => BackupStatus::class,
            'include_users' => 'boolean',
            'include_templates' => 'boolean',
            'include_logs' => 'boolean',
            'logs_from' => 'date:Y-m-d',
            'logs_to' => 'date:Y-m-d',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'completed_at' => 'datetime',
            'counts' => 'array',
            'file_size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DeviceBackupRecord, $this>
     */
    public function records(): HasMany
    {
        return $this->hasMany(DeviceBackupRecord::class);
    }

    public function hasFile(): bool
    {
        return $this->file_path !== null;
    }
}
