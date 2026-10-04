<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\AttendanceDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The processed attendance for one employee, date and shift occurrence. Built by AttendanceProcessor.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $date
 * @property int|null $shift_id
 * @property int|null $branch_id
 * @property DayType $day_type
 * @property AttendanceStatus $status
 * @property Carbon|null $scheduled_start
 * @property Carbon|null $scheduled_end
 * @property int $scheduled_minutes
 * @property Carbon|null $first_in
 * @property Carbon|null $last_out
 * @property int $worked_minutes
 * @property int $late_minutes
 * @property int $early_leave_minutes
 * @property int $overtime_minutes
 * @property int|null $approved_overtime_minutes null = awaiting approval
 * @property bool $is_missing_checkout
 * @property int|null $leave_request_id
 * @property string $leave_fraction
 * @property bool $leave_is_paid
 * @property bool $is_overridden
 * @property string|null $note
 * @property Carbon|null $locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Shift|null $shift
 * @property-read LeaveRequest|null $leaveRequest
 * @property-read Collection<int, AttendanceSession> $sessions
 */
#[Fillable([
    'employee_id', 'date', 'shift_id', 'branch_id', 'day_type', 'status', 'scheduled_start', 'scheduled_end',
    'scheduled_minutes', 'first_in', 'last_out', 'worked_minutes', 'late_minutes', 'early_leave_minutes',
    'overtime_minutes', 'approved_overtime_minutes', 'is_missing_checkout', 'leave_request_id', 'leave_fraction',
    'leave_is_paid', 'is_overridden', 'note', 'locked_at',
])]
class AttendanceDay extends Model
{
    /** @use HasFactory<AttendanceDayFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'day_type' => DayType::class,
            'status' => AttendanceStatus::class,
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'first_in' => 'datetime',
            'last_out' => 'datetime',
            'scheduled_minutes' => 'integer',
            'worked_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
            'overtime_minutes' => 'integer',
            'approved_overtime_minutes' => 'integer',
            'is_missing_checkout' => 'boolean',
            'leave_fraction' => 'decimal:1',
            'leave_is_paid' => 'boolean',
            'is_overridden' => 'boolean',
            'locked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    /**
     * @return BelongsTo<LeaveRequest, $this>
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    /**
     * @return HasMany<AttendanceSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(AttendanceSession::class)->orderBy('check_in');
    }

    /**
     * @param  Builder<AttendanceDay>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    /**
     * Overtime still waiting for a manager.
     *
     * @param  Builder<AttendanceDay>  $query
     */
    public function scopeOvertimePending(Builder $query): void
    {
        $query->where('overtime_minutes', '>', 0)->whereNull('approved_overtime_minutes');
    }
}
