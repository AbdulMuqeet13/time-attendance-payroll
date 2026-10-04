<?php

namespace App\Models\Concerns;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Send timestamps to the frontend in the app timezone ("2026-10-05 09:02:11"), not as UTC ISO strings,
 * so the times shown are the times the device recorded whatever the browser's timezone.
 */
trait SerializesLocalDates
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone(config('app.timezone')))->format('Y-m-d H:i:s');
    }
}
