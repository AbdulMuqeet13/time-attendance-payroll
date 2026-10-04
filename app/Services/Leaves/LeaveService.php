<?php

namespace App\Services\Leaves;

use App\Enums\LeaveStatus;
use App\Exceptions\Leaves\LeaveException;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Attendance\AttendanceRebuilder;
use App\Services\Attendance\ShiftResolver;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Leave requests: create, approve, reject, cancel. Approving or cancelling recalculates the attendance
 * of the covered days. Business rules are enforced here (race-safe), and the form requests show the same
 * errors on the field.
 */
class LeaveService
{
    public function __construct(
        private LeaveBalanceService $balances,
        private ShiftResolver $resolver,
        private AttendanceRebuilder $rebuilder,
    ) {}

    /**
     * Working days in the range: days the employee has a shift that are not holidays. Someone with no
     * roster counts every day but Sunday. A half day is 0.5.
     */
    public function countDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, bool $isHalfDay): float
    {
        $schedule = $this->resolver->scheduleFor($employee, $start, $end);
        $days = 0;

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $date = CarbonImmutable::parse($date);

            if ($schedule->holidayOn($date)) {
                continue;
            }

            $isWorking = $schedule->hasRosterOn($date)
                ? $schedule->occurrencesOn($date)->isNotEmpty()
                : ! $date->isSunday();

            $days += $isWorking ? 1 : 0;
        }

        return $isHalfDay ? min(0.5, $days * 0.5) : (float) $days;
    }

    /**
     * Why the request can't be made, or null when it can.
     *
     * @param  array{leave_type_id: int|string, start_date: string, end_date: string, is_half_day?: bool}  $data
     */
    public function problemWith(Employee $employee, array $data, ?int $ignoreRequestId = null): ?string
    {
        $type = LeaveType::query()->findOrFail($data['leave_type_id']);
        $start = CarbonImmutable::parse($data['start_date']);
        $end = CarbonImmutable::parse($data['end_date']);
        $isHalfDay = (bool) ($data['is_half_day'] ?? false);

        if ($type->gender !== null && $type->gender !== $employee->gender) {
            return "{$type->name} is not available for this employee.";
        }

        if ($isHalfDay && (! $type->allow_half_day || ! $start->isSameDay($end))) {
            return $type->allow_half_day ? 'A half day must start and end on the same date.' : "{$type->name} cannot be taken as a half day.";
        }

        if ($start->lt(CarbonImmutable::parse($employee->joining_date->toDateString()))) {
            return 'The leave starts before the employee joined.';
        }

        $overlapping = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->active()
            ->overlapping($start, $end)
            ->when($ignoreRequestId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->exists();

        if ($overlapping) {
            return 'This overlaps another leave request.';
        }

        $days = $this->countDays($employee, $start, $end, $isHalfDay);

        if ($days <= 0) {
            return 'There are no working days in this period.';
        }

        if ($type->hasQuota()) {
            if ($start->year !== $end->year) {
                return 'Split leave that runs into the next year into two requests.';
            }

            $available = $this->balances->summary($employee, $type, $start->year, $ignoreRequestId)['available'];

            if ($days > $available) {
                return "Only {$available} day(s) of {$type->name} are available, this needs {$days}.";
            }
        }

        return null;
    }

    /**
     * @param  array{leave_type_id: int|string, start_date: string, end_date: string, is_half_day?: bool, reason?: string|null, attachment_path?: string|null}  $data
     *
     * @throws LeaveException
     */
    public function create(Employee $employee, array $data, User $user, bool $autoApprove = false): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data, $user, $autoApprove) {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();

            if ($problem = $this->problemWith($employee, $data)) {
                throw new LeaveException($problem);
            }

            $isHalfDay = (bool) ($data['is_half_day'] ?? false);
            $start = CarbonImmutable::parse($data['start_date']);
            $end = CarbonImmutable::parse($data['end_date']);

            $request = LeaveRequest::query()->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $data['leave_type_id'],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'is_half_day' => $isHalfDay,
                'days' => $this->countDays($employee, $start, $end, $isHalfDay),
                'reason' => $data['reason'] ?? null,
                'attachment_path' => $data['attachment_path'] ?? null,
                'status' => LeaveStatus::Pending,
                'requested_by' => $user->id,
            ]);

            if ($autoApprove) {
                $this->approve($request, $user);
            }

            return $request;
        });
    }

    /**
     * @throws LeaveException
     */
    public function approve(LeaveRequest $request, User $user, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $user, $note) {
            $request = LeaveRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ($request->status !== LeaveStatus::Pending) {
                throw new LeaveException('Only pending requests can be approved.');
            }

            $this->ensureNotLocked($request);

            $request->update(['status' => LeaveStatus::Approved, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
        });

        $this->rebuilder->forEmployee($request->employee_id, $request->start_date, $request->end_date);
    }

    /**
     * @throws LeaveException
     */
    public function reject(LeaveRequest $request, User $user, string $note): void
    {
        if ($request->status !== LeaveStatus::Pending) {
            throw new LeaveException('Only pending requests can be rejected.');
        }

        $request->update(['status' => LeaveStatus::Rejected, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);
    }

    /**
     * Withdraw a pending request or revoke an approved one (before payroll has used it).
     *
     * @throws LeaveException
     */
    public function cancel(LeaveRequest $request, User $user, ?string $note = null): void
    {
        if (! in_array($request->status, [LeaveStatus::Pending, LeaveStatus::Approved], true)) {
            throw new LeaveException('This request is already closed.');
        }

        $wasApproved = $request->status === LeaveStatus::Approved;
        $this->ensureNotLocked($request);

        $request->update(['status' => LeaveStatus::Cancelled, 'decided_by' => $user->id, 'decided_at' => now(), 'decision_note' => $note]);

        if ($wasApproved) {
            $this->rebuilder->forEmployee($request->employee_id, $request->start_date, $request->end_date);
        }
    }

    /**
     * @throws LeaveException
     */
    private function ensureNotLocked(LeaveRequest $request): void
    {
        $locked = AttendanceDay::query()
            ->where('employee_id', $request->employee_id)
            ->between($request->start_date, $request->end_date)
            ->whereNotNull('locked_at')
            ->exists();

        if ($locked) {
            throw new LeaveException('These days are part of an approved payroll and can no longer change.');
        }
    }
}
