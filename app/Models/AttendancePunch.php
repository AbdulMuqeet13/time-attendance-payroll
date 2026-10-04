<?php

namespace App\Models;

use App\Enums\PunchSource;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\AttendancePunchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One raw scan (or manual punch). Never edited; wrong punches are voided.
 *
 * @property int $id
 * @property int|null $device_id
 * @property int|null $employee_id
 * @property string|null $pin
 * @property Carbon $punched_at
 * @property int|null $punch_state
 * @property int|null $verify_type
 * @property string|null $work_code
 * @property PunchSource $source
 * @property string $dedupe_hash
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Device|null $device
 * @property-read Employee|null $employee
 * @property-read User|null $creator
 */
#[Fillable([
    'device_id', 'employee_id', 'pin', 'punched_at', 'punch_state', 'verify_type', 'work_code', 'source',
    'dedupe_hash', 'reason', 'created_by', 'voided_at', 'voided_by', 'void_reason',
])]
class AttendancePunch extends Model
{
    /** @use HasFactory<AttendancePunchFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'voided_at' => 'datetime',
            'source' => PunchSource::class,
            'punch_state' => 'integer',
            'verify_type' => 'integer',
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
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Hash identifying a device scan, so the same scan pushed twice (or restored later) is stored once.
     */
    public static function hashFor(string $serialNumber, string $pin, string $punchedAt): string
    {
        return sha1("{$serialNumber}|{$pin}|{$punchedAt}");
    }

    /**
     * @param  Builder<AttendancePunch>  $query
     */
    public function scopeValid(Builder $query): void
    {
        $query->whereNull('voided_at');
    }

    /**
     * Punches whose PIN matched no employee.
     *
     * @param  Builder<AttendancePunch>  $query
     */
    public function scopeUnmatched(Builder $query): void
    {
        $query->whereNull('employee_id');
    }
}
