<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum AttendanceStatus: string
{
    use HasOptions;

    case Present = 'present';
    case Late = 'late';
    case HalfDay = 'half_day';
    case Absent = 'absent';
    case Leave = 'leave';
    case Holiday = 'holiday';
    case WeeklyOff = 'weekly_off';
    case Scheduled = 'scheduled';
    case Unscheduled = 'unscheduled';

    /**
     * Statuses a manager may set by hand on a scheduled day.
     *
     * @return array<int, self>
     */
    public static function overridable(): array
    {
        return [self::Present, self::HalfDay, self::Absent];
    }

    /**
     * How bad the status is, for showing one status per day when a day has several shifts.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Absent => 6,
            self::Late => 5,
            self::HalfDay => 4,
            self::Leave => 3,
            self::Present => 2,
            self::Scheduled => 1,
            self::Holiday, self::WeeklyOff, self::Unscheduled => 0,
        };
    }
}
