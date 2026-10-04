<?php

namespace App\Services\Attendance;

use App\Models\AttendancePunch;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Turns an employee's punches into check-in / check-out entries matched to shift occurrences.
 *
 * Rules (from the gym app's tested behaviour):
 *  - A punch closes the latest open entry it can: a scheduled entry when the punch is inside that
 *    occurrence's window (start − early … end + grace), or later (long overtime) while it is within
 *    MAX_SESSION_HOURS of the check-in and inside no other shift's window; an unscheduled entry on the
 *    same calendar day.
 *  - A punch within the repeat-scan window of the entry's check-in is a double scan and is ignored.
 *  - Otherwise it checks in, to the occurrence whose window holds it and that has not ended, nearest start
 *    first; with none it is an unscheduled entry.
 *  - A new check-in next to another stored punch is a double scan, unless it starts a different shift
 *    (checking out of one shift and straight into the next).
 *  - A forgotten check-out stays open: the next shift's scan does not close it.
 */
class PunchReplayer
{
    /**
     * The longest a check-in can stay open: a later punch can still be its check-out (overtime),
     * after this it is a forgotten check-out.
     */
    public const MAX_SESSION_HOURS = 16;

    /**
     * @param  Collection<int, AttendancePunch>  $punches  sorted by time
     * @return array<int, PunchEntry>
     */
    public function replay(EmployeeSchedule $schedule, Collection $punches, int $duplicateMinutes): array
    {
        /** @var array<int, PunchEntry> $entries */
        $entries = [];

        foreach ($punches as $punch) {
            $at = CarbonImmutable::parse($punch->punched_at);
            $open = $this->openEntryFor($entries, $schedule, $at);

            if ($open) {
                if ($open->checkIn()->diffInSeconds($at) < $duplicateMinutes * 60) {
                    continue;
                }

                $open->out = $punch;

                continue;
            }

            $occurrence = $this->occurrenceForCheckIn($schedule, $at);
            $nearby = $this->nearbyEntry($entries, $at, $duplicateMinutes);

            if ($nearby && (! $occurrence || $nearby->occurrenceKey() === $this->key($occurrence))) {
                continue;
            }

            $entries[] = new PunchEntry($punch, $occurrence, $occurrence ? $occurrence->date->toDateString() : $at->toDateString());
        }

        return $entries;
    }

    /**
     * @param  array<int, PunchEntry>  $entries
     */
    private function openEntryFor(array $entries, EmployeeSchedule $schedule, CarbonImmutable $at): ?PunchEntry
    {
        $occurrenceKeys = $schedule->occurrencesAround($at)
            ->filter(fn (ShiftOccurrence $occurrence) => $occurrence->contains($at))
            ->map(fn (ShiftOccurrence $occurrence) => $this->key($occurrence))
            ->all();
        $insideAnyWindow = $occurrenceKeys !== [];

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            $entry = $entries[$index];

            if (! $entry->isOpen() || $entry->checkIn()->gt($at)) {
                continue;
            }

            $fits = $entry->occurrence
                ? in_array($entry->occurrenceKey(), $occurrenceKeys, true) || $this->isLongOvertime($entry, $at, $insideAnyWindow)
                : $entry->date === $at->toDateString();

            if ($fits) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * A punch after the check-out grace still checks out (overtime) when it is not inside another shift's
     * window and the session would not exceed MAX_SESSION_HOURS.
     */
    private function isLongOvertime(PunchEntry $entry, CarbonImmutable $at, bool $insideAnyWindow): bool
    {
        return ! $insideAnyWindow
            && $at->gt($entry->occurrence->endsAt)
            && $entry->checkIn()->diffInHours($at) < self::MAX_SESSION_HOURS;
    }

    private function occurrenceForCheckIn(EmployeeSchedule $schedule, CarbonImmutable $at): ?ShiftOccurrence
    {
        return $schedule->occurrencesAround($at)
            ->filter(fn (ShiftOccurrence $occurrence) => $occurrence->contains($at) && $at->lt($occurrence->endsAt))
            ->sortBy(fn (ShiftOccurrence $occurrence) => abs($at->diffInSeconds($occurrence->startsAt)))
            ->first();
    }

    /**
     * @param  array<int, PunchEntry>  $entries
     */
    private function nearbyEntry(array $entries, CarbonImmutable $at, int $duplicateMinutes): ?PunchEntry
    {
        $limit = $duplicateMinutes * 60;

        for ($index = count($entries) - 1; $index >= 0; $index--) {
            $entry = $entries[$index];
            $out = $entry->checkOut();

            if (abs($entry->checkIn()->diffInSeconds($at)) < $limit || ($out && abs($out->diffInSeconds($at)) < $limit)) {
                return $entry;
            }
        }

        return null;
    }

    private function key(ShiftOccurrence $occurrence): string
    {
        return $occurrence->shift->id.'@'.$occurrence->date->toDateString();
    }
}
