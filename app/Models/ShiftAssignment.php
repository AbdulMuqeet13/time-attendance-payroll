<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\ShiftAssignmentFactory;
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
 * @property int $shift_id
 * @property array<int, int>|null $days Weekdays (0 = Sunday … 6 = Saturday); null = every day
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Shift $shift
 */
#[Fillable(['employee_id', 'shift_id', 'days', 'effective_from', 'effective_to', 'created_by'])]
class ShiftAssignment extends Model
{
    /** @use HasFactory<ShiftAssignmentFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days' => 'array',
            'effective_from' => 'date:Y-m-d',
            'effective_to' => 'date:Y-m-d',
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
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }

    /**
     * Whether the shift is scheduled to start on the given date under this assignment.
     */
    public function appliesOn(CarbonInterface $date): bool
    {
        if ($date->lt($this->effective_from->startOfDay()) || ($this->effective_to && $date->gt($this->effective_to->endOfDay()))) {
            return false;
        }

        return empty($this->days) || in_array($date->dayOfWeek, array_map('intval', $this->days), true);
    }

    /**
     * Assignments in effect at any point between the two dates.
     *
     * @param  Builder<ShiftAssignment>  $query
     */
    public function scopeEffectiveBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->where('effective_from', '<=', $to->toDateString())
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
