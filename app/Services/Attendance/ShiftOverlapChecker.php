<?php

namespace App\Services\Attendance;

use Carbon\CarbonImmutable;

/**
 * Detects shift assignments that would have an employee in two shifts at the same time.
 *
 * Two assignments clash when their date ranges overlap and, within a week, their shift times overlap
 * on a shared weekday (overnight shifts included, e.g. Sat 22:00–06:00 against Sun 05:00–09:00).
 */
class ShiftOverlapChecker
{
    private const WEEK_MINUTES = 7 * 24 * 60;

    /**
     * @param  array{start_time: string, end_time: string, days: array<int, int|string>|null, effective_from: string, effective_to: string|null}  $first
     * @param  array{start_time: string, end_time: string, days: array<int, int|string>|null, effective_from: string, effective_to: string|null}  $second
     */
    public function overlaps(array $first, array $second): bool
    {
        return $this->datesOverlap($first, $second) && $this->timesOverlap($first, $second);
    }

    /**
     * @param  array{effective_from: string, effective_to: string|null}  $first
     * @param  array{effective_from: string, effective_to: string|null}  $second
     */
    private function datesOverlap(array $first, array $second): bool
    {
        $date = fn (?string $value, string $fallback): string => $value ? CarbonImmutable::parse($value)->toDateString() : $fallback;

        $firstEnd = $date($first['effective_to'], '9999-12-31');
        $secondEnd = $date($second['effective_to'], '9999-12-31');

        return $date($first['effective_from'], '0000-01-01') <= $secondEnd
            && $date($second['effective_from'], '0000-01-01') <= $firstEnd;
    }

    /**
     * @param  array{start_time: string, end_time: string, days: array<int, int|string>|null}  $first
     * @param  array{start_time: string, end_time: string, days: array<int, int|string>|null}  $second
     */
    private function timesOverlap(array $first, array $second): bool
    {
        foreach ($this->weeklyIntervals($first) as [$startA, $endA]) {
            foreach ($this->weeklyIntervals($second) as [$startB, $endB]) {
                if ($startA < $endB && $startB < $endA) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The shift as minute ranges within a week (Sunday 00:00 = 0). Ranges running past the end of
     * Saturday wrap to the start of the week.
     *
     * @param  array{start_time: string, end_time: string, days: array<int, int|string>|null}  $shift
     * @return array<int, array{int, int}>
     */
    private function weeklyIntervals(array $shift): array
    {
        $minutesOf = fn (string $time): int => ((int) substr($time, 0, 2)) * 60 + (int) substr($time, 3, 2);

        $start = $minutesOf($shift['start_time']);
        $end = $minutesOf($shift['end_time']);
        $length = $end > $start ? $end - $start : $end + 24 * 60 - $start;
        $days = empty($shift['days']) ? range(0, 6) : array_map('intval', $shift['days']);

        $intervals = [];

        foreach ($days as $day) {
            $from = $day * 24 * 60 + $start;
            $to = $from + $length;

            if ($to > self::WEEK_MINUTES) {
                $intervals[] = [$from, self::WEEK_MINUTES];
                $intervals[] = [0, $to - self::WEEK_MINUTES];
            } else {
                $intervals[] = [$from, $to];
            }
        }

        return $intervals;
    }
}
