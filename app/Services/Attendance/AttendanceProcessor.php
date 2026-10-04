<?php

namespace App\Services\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\DayType;
use App\Models\AttendanceDay;
use App\Models\AttendanceOverride;
use App\Models\AttendancePunch;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds attendance days from scratch: punches + roster + holidays + approved leave + manager overrides.
 *
 * Rebuilding is deterministic and safe to repeat, so late device uploads, restored backups, roster changes
 * and manual punches all just rebuild the affected dates. Days locked by an approved payroll are never touched.
 *
 * Pay-relevant rules:
 *  - Worked time counts from the shift start (arriving early is not credited); an unpaid break not taken
 *    as a gap between sessions is deducted.
 *  - Late = check-in after start + the shift's late grace; late minutes are counted from the start.
 *  - Overtime = time after the shift end when it reaches the minimum; on holidays and weekly offs all
 *    worked time is overtime. Overtime is paid once approved (or straight away if approval is off).
 *  - Half day = worked less than the shift's half-day minutes (by default the company setting, but never
 *    more than half the shift).
 *  - A check-in still open PunchReplayer::MAX_SESSION_HOURS later is a forgotten check-out: flagged and
 *    credited up to the shift end, without overtime.
 */
class AttendanceProcessor
{
    public function __construct(
        private ShiftResolver $resolver,
        private PunchReplayer $replayer,
        private Settings $settings,
    ) {}

    /**
     * @return int The number of attendance rows written
     */
    public function rebuild(Employee $employee, CarbonInterface $from, CarbonInterface $to): int
    {
        $from = CarbonImmutable::parse($from->toDateString());
        $to = CarbonImmutable::parse($to->toDateString());
        [$firstDay, $lastDay] = $this->employedRange($employee, $from, $to);

        $schedule = $this->resolver->scheduleFor($employee, $from->subDay(), $to->addDay());
        $entries = $this->replayer->replay($schedule, $this->punches($employee, $from->subDay(), $to->addDays(2)), $this->settings->duplicateScanMinutes());

        $lockedDates = AttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->between($from, $to)
            ->whereNotNull('locked_at')
            ->pluck('date')
            ->map(fn (CarbonInterface $date) => $date->toDateString())
            ->unique()
            ->all();

        $leaves = LeaveRequest::query()->approved()->where('employee_id', $employee->id)->overlapping($from, $to)->with('leaveType')->get();
        $overrides = AttendanceOverride::query()->where('employee_id', $employee->id)->whereBetween('date', [$from->toDateString(), $to->toDateString()])->get();

        $rows = [];

        if ($firstDay !== null) {
            foreach (CarbonPeriod::create($firstDay, $lastDay) as $date) {
                $date = CarbonImmutable::parse($date);

                if (in_array($date->toDateString(), $lockedDates, true)) {
                    continue;
                }

                array_push($rows, ...$this->buildDate($employee, $date, $schedule, $entries, $leaves, $overrides));
            }
        }

        DB::transaction(function () use ($employee, $from, $to, $rows) {
            AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->between($from, $to)
                ->whereNull('locked_at')
                ->delete();

            foreach ($rows as $row) {
                $sessions = $row['sessions'];
                unset($row['sessions']);

                AttendanceDay::query()->create($row)->sessions()->createMany($sessions);
            }
        });

        return count($rows);
    }

    /**
     * The part of the range the employee was on the payroll, never later than today.
     *
     * @return array{0: CarbonImmutable|null, 1: CarbonImmutable|null}
     */
    private function employedRange(Employee $employee, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $first = $from->max(CarbonImmutable::parse($employee->joining_date->toDateString()));
        $last = $to->min(CarbonImmutable::today());

        if ($employee->exit_date) {
            $last = $last->min(CarbonImmutable::parse($employee->exit_date->toDateString()));
        }

        return $first->lte($last) ? [$first, $last] : [null, null];
    }

    /**
     * @return Collection<int, AttendancePunch>
     */
    private function punches(Employee $employee, CarbonImmutable $from, CarbonImmutable $until): Collection
    {
        return AttendancePunch::query()
            ->valid()
            ->where('employee_id', $employee->id)
            ->where('punched_at', '>=', $from->startOfDay()->toDateTimeString())
            ->where('punched_at', '<', $until->startOfDay()->toDateTimeString())
            ->orderBy('punched_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Rows for one date: one per shift occurrence, plus one for scans outside any shift; or a single
     * row for a day without shifts (weekly off, holiday or unscheduled).
     *
     * @param  array<int, PunchEntry>  $entries
     * @param  Collection<int, LeaveRequest>  $leaves
     * @param  Collection<int, AttendanceOverride>  $overrides
     * @return array<int, array<string, mixed>>
     */
    private function buildDate(Employee $employee, CarbonImmutable $date, EmployeeSchedule $schedule, array $entries, Collection $leaves, Collection $overrides): array
    {
        $day = $date->toDateString();
        $holiday = $schedule->holidayOn($date);
        $leave = $leaves->first(fn (LeaveRequest $leave) => $leave->covers($date));
        $occurrences = $schedule->occurrencesOn($date);
        $unscheduled = array_values(array_filter($entries, fn (PunchEntry $entry) => $entry->occurrence === null && $entry->date === $day));
        $rows = [];

        foreach ($occurrences as $occurrence) {
            $sessions = array_values(array_filter($entries, fn (PunchEntry $entry) => $entry->occurrenceKey() === $occurrence->shift->id.'@'.$day));
            $rows[] = $this->scheduledRow($employee, $occurrence, $sessions, $holiday, $leave);
        }

        if ($occurrences->isEmpty()) {
            $rows[] = $this->offDayRow($employee, $date, $unscheduled, $holiday, $schedule->hasRosterOn($date));
        } elseif ($unscheduled !== []) {
            $rows[] = [...$this->offDayRow($employee, $date, $unscheduled, null, true), 'day_type' => DayType::Working, 'status' => AttendanceStatus::Unscheduled, 'overtime_minutes' => 0];
        }

        return array_map(fn (array $row) => $this->applyOverride($row, $overrides), $rows);
    }

    /**
     * @param  array<int, PunchEntry>  $entries
     * @return array<string, mixed>
     */
    private function scheduledRow(Employee $employee, ShiftOccurrence $occurrence, array $entries, ?Holiday $holiday, ?LeaveRequest $leave): array
    {
        $shift = $occurrence->shift;
        $now = CarbonImmutable::now();
        $windowPassed = $now->gt($occurrence->windowEnd);
        $hasOpen = collect($entries)->contains(fn (PunchEntry $entry) => $entry->isOpen());
        $missingCheckout = $hasOpen && collect($entries)->contains(fn (PunchEntry $entry) => $entry->isOpen()
            && $entry->checkIn()->addHours(PunchReplayer::MAX_SESSION_HOURS)->lte($now));
        $halfDayMinutes = $shift->half_day_minutes ?? min($shift->halfDayMinutes(), intdiv($occurrence->scheduledMinutes(), 2));

        $firstIn = $entries === [] ? null : $entries[0]->checkIn();
        $lastOut = $entries === [] || $hasOpen ? null : end($entries)->checkOut();

        $worked = $this->workedMinutes($entries, $occurrence->startsAt, $occurrence->endsAt, $shift->break_minutes, $missingCheckout, $now);
        $late = $firstIn && $firstIn->gt($occurrence->startsAt) ? (int) $occurrence->startsAt->diffInMinutes($firstIn) : 0;
        $earlyLeave = $lastOut && $lastOut->lt($occurrence->endsAt) ? (int) $lastOut->diffInMinutes($occurrence->endsAt) : 0;
        $overtime = $lastOut && $lastOut->gt($occurrence->endsAt) ? (int) $occurrence->endsAt->diffInMinutes($lastOut) : 0;
        $overtime = $overtime >= $shift->minOvertimeMinutes() ? $overtime : 0;

        $leaveFraction = $leave ? ($leave->is_half_day ? 0.5 : 1.0) : 0.0;

        if ($holiday) {
            $status = AttendanceStatus::Holiday;
            $overtime = $worked >= $shift->minOvertimeMinutes() ? $worked : 0;
            $late = 0;
            $earlyLeave = 0;
        } elseif ($leaveFraction === 1.0) {
            $status = AttendanceStatus::Leave;
        } elseif ($entries === []) {
            $status = ! $windowPassed ? AttendanceStatus::Scheduled : ($leaveFraction > 0 ? AttendanceStatus::HalfDay : AttendanceStatus::Absent);
        } elseif ($leaveFraction === 0.0 && ($missingCheckout || ! $hasOpen) && $worked < $halfDayMinutes) {
            $status = AttendanceStatus::HalfDay;
        } else {
            $status = $late > $shift->lateGraceMinutes() && $leaveFraction === 0.0 ? AttendanceStatus::Late : AttendanceStatus::Present;
        }

        return [
            'employee_id' => $employee->id,
            'date' => $occurrence->date->toDateString(),
            'shift_id' => $shift->id,
            'branch_id' => $employee->branch_id,
            'day_type' => $holiday ? DayType::Holiday : DayType::Working,
            'status' => $status,
            'scheduled_start' => $occurrence->startsAt,
            'scheduled_end' => $occurrence->endsAt,
            'scheduled_minutes' => $occurrence->scheduledMinutes(),
            'first_in' => $firstIn,
            'last_out' => $lastOut,
            'worked_minutes' => $worked,
            'late_minutes' => $status === AttendanceStatus::Late ? $late : 0,
            'early_leave_minutes' => in_array($status, [AttendanceStatus::Present, AttendanceStatus::Late], true) ? $earlyLeave : 0,
            'overtime_minutes' => $overtime,
            'is_missing_checkout' => $missingCheckout,
            'leave_request_id' => $leave?->id,
            'leave_fraction' => $leaveFraction,
            'leave_is_paid' => (bool) $leave?->leaveType->is_paid,
            'sessions' => $this->sessions($entries),
        ];
    }

    /**
     * A day without shifts: holiday, weekly off, or unscheduled (no roster at all). Any work is overtime
     * on holidays and weekly offs.
     *
     * @param  array<int, PunchEntry>  $entries
     * @return array<string, mixed>
     */
    private function offDayRow(Employee $employee, CarbonImmutable $date, array $entries, ?Holiday $holiday, bool $hasRoster): array
    {
        $now = CarbonImmutable::now();
        $worked = 0;

        foreach ($entries as $entry) {
            $out = $entry->checkOut() ?? ($date->isToday() ? $now : null);
            $worked += $out ? (int) $entry->checkIn()->diffInMinutes($out) : 0;
        }

        [$dayType, $status] = match (true) {
            $holiday !== null => [DayType::Holiday, AttendanceStatus::Holiday],
            $hasRoster => [DayType::WeeklyOff, AttendanceStatus::WeeklyOff],
            default => [DayType::Working, AttendanceStatus::Unscheduled],
        };

        $isOffDay = $dayType !== DayType::Working;
        $closedEntries = array_filter($entries, fn (PunchEntry $entry) => ! $entry->isOpen());

        return [
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
            'shift_id' => null,
            'branch_id' => $employee->branch_id,
            'day_type' => $dayType,
            'status' => $status,
            'scheduled_start' => null,
            'scheduled_end' => null,
            'scheduled_minutes' => 0,
            'first_in' => $entries === [] ? null : $entries[0]->checkIn(),
            'last_out' => $closedEntries === [] ? null : end($closedEntries)->checkOut(),
            'worked_minutes' => $worked,
            'late_minutes' => 0,
            'early_leave_minutes' => 0,
            'overtime_minutes' => $isOffDay && $worked >= $this->settings->minOvertimeMinutes() ? $worked : 0,
            'is_missing_checkout' => collect($entries)->contains(fn (PunchEntry $entry) => $entry->isOpen()
                && $entry->checkIn()->addHours(PunchReplayer::MAX_SESSION_HOURS)->lte(CarbonImmutable::now())),
            'leave_request_id' => null,
            'leave_fraction' => 0,
            'leave_is_paid' => false,
            'sessions' => $this->sessions($entries),
        ];
    }

    /**
     * Worked minutes within the shift: sessions clipped to start at the shift start, an open session
     * counted to now (still at work) or to the shift end (forgot to check out), minus any unpaid break
     * not taken as a gap between sessions.
     *
     * @param  array<int, PunchEntry>  $entries
     */
    private function workedMinutes(array $entries, CarbonImmutable $start, CarbonImmutable $end, int $breakMinutes, bool $missingCheckout, CarbonImmutable $now): int
    {
        $worked = 0;
        $gaps = 0;
        $previousOut = null;

        foreach ($entries as $entry) {
            $in = $entry->checkIn()->max($start);
            $out = $entry->checkOut() ?? ($missingCheckout ? $end->max($in) : $now);

            if ($previousOut && $entry->checkIn()->gt($previousOut)) {
                $gaps += (int) $previousOut->diffInMinutes($entry->checkIn());
            }

            $worked += $out->gt($in) ? (int) $in->diffInMinutes($out) : 0;
            $previousOut = $entry->checkOut();
        }

        $untakenBreak = max(0, $breakMinutes - $gaps);

        return $worked > $untakenBreak ? $worked - $untakenBreak : $worked;
    }

    /**
     * @param  array<int, PunchEntry>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function sessions(array $entries): array
    {
        return array_map(fn (PunchEntry $entry) => [
            'check_in' => $entry->checkIn(),
            'check_out' => $entry->checkOut(),
            'in_punch_id' => $entry->in->id,
            'out_punch_id' => $entry->out?->id,
            'minutes' => $entry->checkOut() ? (int) $entry->checkIn()->diffInMinutes($entry->checkOut()) : 0,
        ], $entries);
    }

    /**
     * Apply a manager's decision and settle overtime approval.
     *
     * @param  array<string, mixed>  $row
     * @param  Collection<int, AttendanceOverride>  $overrides
     * @return array<string, mixed>
     */
    private function applyOverride(array $row, Collection $overrides): array
    {
        $override = $overrides->first(fn (AttendanceOverride $override) => $override->date->toDateString() === $row['date']
            && $override->shift_id === $row['shift_id']);

        if ($override?->status) {
            $row['status'] = $override->status;
            $row['is_overridden'] = true;
            $row['late_minutes'] = $override->status === AttendanceStatus::Late ? $row['late_minutes'] : 0;
        }

        if ($override?->waive_late && $row['status'] === AttendanceStatus::Late) {
            $row['status'] = AttendanceStatus::Present;
            $row['late_minutes'] = 0;
            $row['is_overridden'] = true;
        }

        $row['note'] = $override?->reason;

        if ($row['overtime_minutes'] === 0) {
            $row['approved_overtime_minutes'] = 0;
        } elseif ($override?->approved_overtime_minutes !== null) {
            $row['approved_overtime_minutes'] = min($override->approved_overtime_minutes, $row['overtime_minutes']);
        } else {
            $row['approved_overtime_minutes'] = $this->settings->overtimeRequiresApproval() ? null : $row['overtime_minutes'];
        }

        return $row;
    }
}
