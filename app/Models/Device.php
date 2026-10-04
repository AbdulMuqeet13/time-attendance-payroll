<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A ZKTeco device using the ADMS push protocol.
 *
 * @property int $id
 * @property string $serial_number
 * @property string $name
 * @property int|null $branch_id null = unclaimed (data ignored)
 * @property bool $is_active
 * @property string|null $model
 * @property string|null $firmware
 * @property string|null $push_version
 * @property string|null $ip_address
 * @property Carbon|null $last_seen_at
 * @property string|null $last_attlog_stamp
 * @property string|null $last_operlog_stamp
 * @property int|null $user_count
 * @property int|null $fp_count
 * @property int|null $face_count
 * @property int|null $att_count
 * @property string|null $fp_algorithm
 * @property string|null $face_algorithm
 * @property string $auto_backup none|daily|weekly
 * @property int $backup_retention
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch|null $branch
 */
#[Fillable([
    'serial_number', 'name', 'branch_id', 'is_active', 'model', 'firmware', 'push_version', 'ip_address',
    'last_seen_at', 'last_attlog_stamp', 'last_operlog_stamp', 'user_count', 'fp_count', 'face_count', 'att_count',
    'fp_algorithm', 'face_algorithm', 'auto_backup', 'backup_retention',
])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * A device that polled within this many seconds is shown as online.
     */
    public const ONLINE_SECONDS = 120;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
            'user_count' => 'integer',
            'fp_count' => 'integer',
            'face_count' => 'integer',
            'att_count' => 'integer',
            'backup_retention' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return HasMany<DeviceCommand, $this>
     */
    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    /**
     * @return HasMany<DeviceUser, $this>
     */
    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    /**
     * @return HasMany<AttendancePunch, $this>
     */
    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    /**
     * Claimed by a branch and enabled: only then are its scans recorded.
     */
    public function acceptsData(): bool
    {
        return $this->branch_id !== null && $this->is_active;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null && $this->last_seen_at->gt(now()->subSeconds(self::ONLINE_SECONDS));
    }

    /**
     * Whether the firmware speaks PUSH protocol 3.x (table queries via "tablename=…").
     */
    public function usesPushV3(): bool
    {
        return $this->push_version !== null && version_compare($this->push_version, '3.0', '>=');
    }

    /**
     * @param  Builder<Device>  $query
     */
    public function scopeClaimed(Builder $query): void
    {
        $query->whereNotNull('branch_id');
    }

    /**
     * Devices the user may see: users tied to a branch see their branch's devices.
     *
     * @param  Builder<Device>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->when($user->branch_id, fn (Builder $query, int $branchId) => $query->where('branch_id', $branchId));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'branch_id', 'is_active', 'auto_backup', 'backup_retention'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
