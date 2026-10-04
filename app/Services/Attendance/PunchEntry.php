<?php

namespace App\Services\Attendance;

use App\Models\AttendancePunch;
use Carbon\CarbonImmutable;

/**
 * A check-in, and its check-out once paired, produced while replaying punches.
 */
final class PunchEntry
{
    public ?AttendancePunch $out = null;

    public function __construct(
        public readonly AttendancePunch $in,
        public readonly ?ShiftOccurrence $occurrence,
        /** The shift date for scheduled entries, the punch date for unscheduled ones. */
        public readonly string $date,
    ) {}

    public function checkIn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->in->punched_at);
    }

    public function checkOut(): ?CarbonImmutable
    {
        return $this->out ? CarbonImmutable::parse($this->out->punched_at) : null;
    }

    public function isOpen(): bool
    {
        return $this->out === null;
    }

    public function occurrenceKey(): ?string
    {
        return $this->occurrence ? $this->occurrence->shift->id.'@'.$this->occurrence->date->toDateString() : null;
    }
}
