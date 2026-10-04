<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\LeaveRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_half_day
 * @property string $days Working days covered (weekly offs and holidays excluded)
 * @property string|null $reason
 * @property string|null $attachment_path
 * @property LeaveStatus $status
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 * @property-read User|null $requester
 * @property-read User|null $decider
 */
#[Fillable([
    'employee_id', 'leave_type_id', 'start_date', 'end_date', 'is_half_day', 'days', 'reason', 'attachment_path',
    'status', 'requested_by', 'decided_by', 'decided_at', 'decision_note',
])]
class LeaveRequest extends Model
{
    /** @use HasFactory<LeaveRequestFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'is_half_day' => 'boolean',
            'days' => 'decimal:1',
            'status' => LeaveStatus::class,
            'decided_at' => 'datetime',
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
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function covers(CarbonInterface $date): bool
    {
        return $date->toDateString() >= $this->start_date->toDateString() && $date->toDateString() <= $this->end_date->toDateString();
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', LeaveStatus::Approved);
    }

    /**
     * Requests that still hold days (pending or approved).
     *
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereIn('status', [LeaveStatus::Pending, LeaveStatus::Approved]);
    }

    /**
     * @param  Builder<LeaveRequest>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('start_date', '<=', $to->toDateString())->where('end_date', '>=', $from->toDateString());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'start_date', 'end_date', 'leave_type_id', 'days', 'decision_note'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
