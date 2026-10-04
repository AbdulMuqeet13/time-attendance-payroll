<?php

namespace App\Services\Attendance;

use App\Models\Shift;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * One scheduled run of a shift on a specific date. Overnight shifts belong to the date they start.
 */
final readonly class ShiftOccurrence
{
    public CarbonImmutable $startsAt;

    public CarbonImmutable $endsAt;

    /** The earliest punch that counts for this occurrence: start − early window. */
    public CarbonImmutable $windowStart;

    /** The latest punch that counts as this occurrence's check-out: end + check-out grace. */
    public CarbonImmutable $windowEnd;

    public function __construct(public Shift $shift, public CarbonImmutable $date)
    {
        $this->startsAt = $shift->startsAt($date);
        $this->endsAt = $shift->endsAt($date);
        $this->windowStart = $this->startsAt->subMinutes($shift->earlyWindowMinutes());
        $this->windowEnd = $this->endsAt->addMinutes($shift->checkoutGraceMinutes());
    }

    public function contains(CarbonInterface $at): bool
    {
        return $at->gte($this->windowStart) && $at->lte($this->windowEnd);
    }

    public function scheduledMinutes(): int
    {
        return $this->shift->scheduledMinutes();
    }
}
