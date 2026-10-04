<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\ShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A shift template (e.g. "Morning 09:00–17:00"). Employees get shifts through ShiftAssignment.
 *
 * Shifts are soft deleted so past attendance keeps pointing at the shift it was recorded against.
 *
 * @property int $id
 * @property string $name
 * @property string $start_time HH:MM
 * @property string $end_time HH:MM
 * @property int $break_minutes
 * @property int|null $late_grace_minutes
 * @property int|null $early_window_minutes
 * @property int|null $checkout_grace_minutes
 * @property int|null $half_day_minutes
 * @property int|null $min_overtime_minutes
 * @property string $color
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'name', 'start_time', 'end_time', 'break_minutes', 'late_grace_minutes', 'early_window_minutes',
    'checkout_grace_minutes', 'half_day_minutes', 'min_overtime_minutes', 'color', 'is_active',
])]
class Shift extends Model
{
    /** @use HasFactory<ShiftFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'late_grace_minutes' => 'integer',
            'early_window_minutes' => 'integer',
            'checkout_grace_minutes' => 'integer',
            'half_day_minutes' => 'integer',
            'min_overtime_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ShiftAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ShiftAssignment::class);
    }

    /**
     * Start time as HH:MM (the database stores HH:MM:SS).
     *
     * @return Attribute<string|null, never>
     */
    protected function startTime(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? substr($value, 0, 5) : null);
    }

    /**
     * End time as HH:MM (the database stores HH:MM:SS).
     *
     * @return Attribute<string|null, never>
     */
    protected function endTime(): Attribute
    {
        return Attribute::make(get: fn (?string $value) => $value ? substr($value, 0, 5) : null);
    }

    /**
     * Whether the shift ends on the day after it starts.
     */
    public function isOvernight(): bool
    {
        return $this->end_time <= $this->start_time;
    }

    /**
     * When the shift starts on the given shift date.
     */
    public function startsAt(CarbonInterface|string $date): CarbonImmutable
    {
        return CarbonImmutable::parse(CarbonImmutable::parse($date)->toDateString().' '.$this->start_time);
    }

    /**
     * When the shift ends for the given shift date (the next day for overnight shifts).
     */
    public function endsAt(CarbonInterface|string $date): CarbonImmutable
    {
        $end = CarbonImmutable::parse(CarbonImmutable::parse($date)->toDateString().' '.$this->end_time);

        return $this->isOvernight() ? $end->addDay() : $end;
    }

    /**
     * Minutes from start to end, before the unpaid break.
     */
    public function spanMinutes(): int
    {
        return (int) $this->startsAt('2000-01-03')->diffInMinutes($this->endsAt('2000-01-03'));
    }

    /**
     * Minutes the employee is expected to work: the span less the break.
     */
    public function scheduledMinutes(): int
    {
        return max(0, $this->spanMinutes() - $this->break_minutes);
    }

    public function lateGraceMinutes(): int
    {
        return $this->late_grace_minutes ?? app(Settings::class)->lateGraceMinutes();
    }

    public function earlyWindowMinutes(): int
    {
        return $this->early_window_minutes ?? app(Settings::class)->earlyWindowMinutes();
    }

    public function checkoutGraceMinutes(): int
    {
        return $this->checkout_grace_minutes ?? app(Settings::class)->checkoutGraceMinutes();
    }

    public function halfDayMinutes(): int
    {
        return $this->half_day_minutes ?? app(Settings::class)->halfDayMinutes();
    }

    public function minOvertimeMinutes(): int
    {
        return $this->min_overtime_minutes ?? app(Settings::class)->minOvertimeMinutes();
    }

    /**
     * Human readable label, e.g. "Morning (09:00–17:00)".
     */
    public function label(): string
    {
        return "{$this->name} ({$this->start_time}–{$this->end_time})";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
