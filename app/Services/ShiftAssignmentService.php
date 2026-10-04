<?php

namespace App\Services;

use App\Exceptions\Attendance\ShiftOverlapException;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Models\User;
use App\Services\Attendance\AttendanceRebuilder;
use App\Services\Attendance\ShiftOverlapChecker;
use Illuminate\Support\Facades\DB;

class ShiftAssignmentService
{
    public function __construct(private ShiftOverlapChecker $overlapChecker, private AttendanceRebuilder $rebuilder) {}

    /**
     * Assign a shift to several employees at once.
     *
     * @param  array<int, int>  $employeeIds
     * @param  array{shift_id: int|string, days?: array<int, int|string>|null, effective_from: string, effective_to?: string|null}  $data
     * @return int The number of assignments created
     *
     * @throws ShiftOverlapException When any employee already works overlapping times
     */
    public function assign(array $employeeIds, array $data, ?User $user): int
    {
        $data = $this->normalise($data);

        return DB::transaction(function () use ($employeeIds, $data, $user) {
            $employees = Employee::query()->whereKey($employeeIds)->lockForUpdate()->get();
            $clashing = $this->clashingEmployees($employees->pluck('id')->all(), $data);

            if ($clashing !== []) {
                throw ShiftOverlapException::forEmployees($clashing);
            }

            foreach ($employees as $employee) {
                $employee->shiftAssignments()->create([...$data, 'created_by' => $user?->id]);
                $this->rebuilder->forEmployee($employee, $data['effective_from'], $data['effective_to'] ?? now());
            }

            return $employees->count();
        });
    }

    /**
     * @param  array{shift_id: int|string, days?: array<int, int|string>|null, effective_from: string, effective_to?: string|null}  $data
     *
     * @throws ShiftOverlapException
     */
    public function update(ShiftAssignment $assignment, array $data): ShiftAssignment
    {
        $data = $this->normalise($data);

        return DB::transaction(function () use ($assignment, $data) {
            Employee::query()->whereKey($assignment->employee_id)->lockForUpdate()->first();

            if ($this->clashingEmployees([$assignment->employee_id], $data, $assignment->id) !== []) {
                throw ShiftOverlapException::forEmployees([$assignment->employee->name]);
            }

            $earliest = min($assignment->effective_from->toDateString(), $data['effective_from']);
            $assignment->update($data);
            $this->rebuilder->forEmployee($assignment->employee_id, $earliest, now());

            return $assignment;
        });
    }

    /**
     * Names of the employees whose existing assignments overlap the proposed one.
     *
     * @param  array<int, int>  $employeeIds
     * @param  array{shift_id: int|string, days?: array<int, int|string>|null, effective_from: string, effective_to?: string|null}  $data
     * @return array<int, string>
     */
    public function clashingEmployees(array $employeeIds, array $data, ?int $ignoreAssignmentId = null): array
    {
        $data = $this->normalise($data);
        $shift = Shift::withTrashed()->findOrFail($data['shift_id']);

        $candidate = [
            'start_time' => $shift->start_time,
            'end_time' => $shift->end_time,
            'days' => $data['days'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'],
        ];

        return ShiftAssignment::query()
            ->whereIn('employee_id', $employeeIds)
            ->when($ignoreAssignmentId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->with(['shift', 'employee:id,name'])
            ->get()
            ->filter(fn (ShiftAssignment $existing) => $this->overlapChecker->overlaps($candidate, [
                'start_time' => $existing->shift->start_time,
                'end_time' => $existing->shift->end_time,
                'days' => $existing->days,
                'effective_from' => $existing->effective_from->toDateString(),
                'effective_to' => $existing->effective_to?->toDateString(),
            ]))
            ->map(fn (ShiftAssignment $existing) => $existing->employee->name)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Weekdays as sorted integers; all seven days (or none) is stored as null, meaning every day.
     *
     * @param  array<string, mixed>  $data
     * @return array{shift_id: int, days: array<int, int>|null, effective_from: string, effective_to: string|null}
     */
    private function normalise(array $data): array
    {
        $days = array_values(array_unique(array_map('intval', $data['days'] ?? [])));
        sort($days);

        return [
            'shift_id' => (int) $data['shift_id'],
            'days' => $days === [] || count($days) === 7 ? null : $days,
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
        ];
    }
}
