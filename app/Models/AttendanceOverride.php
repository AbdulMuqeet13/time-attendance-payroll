<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\AttendanceOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A manager's decision on a computed attendance day; re-applied on every rebuild.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property AttendanceStatus|null $status
 * @property bool $waive_late
 * @property int|null $approved_overtime_minutes
 * @property string|null $reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read User|null $creator
 */
#[Fillable(['employee_id', 'date', 'shift_id', 'status', 'waive_late', 'approved_overtime_minutes', 'reason', 'created_by'])]
class AttendanceOverride extends Model
{
    /** @use HasFactory<AttendanceOverrideFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'status' => AttendanceStatus::class,
            'waive_late' => 'boolean',
            'approved_overtime_minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
