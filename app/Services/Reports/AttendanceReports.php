<?php

namespace App\Services\Reports;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Per-employee summaries over a date range. Each report returns rows of plain values (ready for a table or a sheet).
 */
class AttendanceReports
{
    public const REPORTS = ['attendance', 'lates', 'overtime', 'leave'];

    /**
     * @param  array{branch_id?: int|string|null, department_id?: int|string|null}  $filters
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}
     */
    public function run(string $report, User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): array
    {
        $employees = Employee::query()
            ->visibleTo($user)
            ->employedBetween($from, $to)
            ->when($filters['branch_id'] ?? null, fn ($query, $id) => $query->where('branch_id', $id))
            ->when($filters['department_id'] ?? null, fn ($query, $id) => $query->where('department_id', $id))
            ->with('department:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_code', 'department_id']);

        return match ($report) {
            'attendance' => $this->attendance($employees, $from, $to),
            'lates' => $this->lates($employees, $from, $to),
            'overtime' => $this->overtime($employees, $from, $to),
            'leave' => $this->leave($employees, $from, $to),
            default => throw new InvalidArgumentException("Unknown report [{$report}]."),
        };
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}
     */
    private function attendance(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $this->days($employees, $from, $to);

        return [
            'columns' => $this->columns(['scheduled' => 'Shifts', 'present' => 'Present', 'late' => 'Late', 'half_day' => 'Half day', 'absent' => 'Absent', 'leave' => 'Leave', 'holiday' => 'Holidays', 'worked_hours' => 'Worked h', 'overtime_hours' => 'OT h (approved)', 'missing_checkouts' => 'No check-out']),
            'rows' => $employees->map(function (Employee $employee) use ($days) {
                $rows = $days->get($employee->id, collect());
                $scheduled = $rows->whereNotNull('shift_id');
                $count = fn (AttendanceStatus $status) => $scheduled->where('status', $status)->count();

                return [
                    ...$this->employeeColumns($employee),
                    'scheduled' => $scheduled->count(),
                    'present' => $count(AttendanceStatus::Present) + $count(AttendanceStatus::Late),
                    'late' => $count(AttendanceStatus::Late),
                    'half_day' => $count(AttendanceStatus::HalfDay),
                    'absent' => $count(AttendanceStatus::Absent),
                    'leave' => $count(AttendanceStatus::Leave),
                    'holiday' => $rows->where('status', AttendanceStatus::Holiday)->pluck('date')->unique()->count(),
                    'worked_hours' => round($rows->sum('worked_minutes') / 60, 2),
                    'overtime_hours' => round($rows->sum(fn (AttendanceDay $day) => $day->approved_overtime_minutes ?? 0) / 60, 2),
                    'missing_checkouts' => $rows->where('is_missing_checkout', true)->count(),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}
     */
    private function lates(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $this->days($employees, $from, $to);

        return [
            'columns' => $this->columns(['late_count' => 'Lates', 'late_minutes' => 'Late minutes', 'average_minutes' => 'Avg minutes', 'early_leaves' => 'Left early', 'early_minutes' => 'Early minutes']),
            'rows' => $employees->map(function (Employee $employee) use ($days) {
                $rows = $days->get($employee->id, collect());
                $lates = $rows->where('status', AttendanceStatus::Late);
                $early = $rows->where('early_leave_minutes', '>', 0);

                return [
                    ...$this->employeeColumns($employee),
                    'late_count' => $lates->count(),
                    'late_minutes' => $lates->sum('late_minutes'),
                    'average_minutes' => $lates->isEmpty() ? 0 : (int) round($lates->avg('late_minutes')),
                    'early_leaves' => $early->count(),
                    'early_minutes' => $early->sum('early_leave_minutes'),
                ];
            })->filter(fn (array $row) => $row['late_count'] > 0 || $row['early_leaves'] > 0)->sortByDesc('late_count')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}
     */
    private function overtime(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $days = $this->days($employees, $from, $to);

        return [
            'columns' => $this->columns(['worked_hours' => 'Overtime h', 'approved_hours' => 'Approved h', 'pending_hours' => 'Pending h', 'holiday_hours' => 'Of which holiday/off h']),
            'rows' => $employees->map(function (Employee $employee) use ($days) {
                $rows = $days->get($employee->id, collect())->where('overtime_minutes', '>', 0);

                return [
                    ...$this->employeeColumns($employee),
                    'worked_hours' => round($rows->sum('overtime_minutes') / 60, 2),
                    'approved_hours' => round($rows->sum(fn (AttendanceDay $day) => $day->approved_overtime_minutes ?? 0) / 60, 2),
                    'pending_hours' => round($rows->whereNull('approved_overtime_minutes')->sum('overtime_minutes') / 60, 2),
                    'holiday_hours' => round($rows->where('day_type.value', '!=', 'working')->sum('overtime_minutes') / 60, 2),
                ];
            })->filter(fn (array $row) => $row['worked_hours'] > 0)->sortByDesc('worked_hours')->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return array{columns: array<int, array{key: string, label: string}>, rows: array<int, array<string, mixed>>}
     */
    private function leave(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $requests = LeaveRequest::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('status', LeaveStatus::Approved)
            ->overlapping($from, $to)
            ->with('leaveType:id,name,is_paid')
            ->get()
            ->groupBy('employee_id');

        $types = $requests->flatten()->pluck('leaveType')->unique('id')->sortBy('name');

        return [
            'columns' => $this->columns([
                ...$types->mapWithKeys(fn ($type) => ['type_'.$type->id => $type->name])->all(),
                'total' => 'Total days',
            ]),
            'rows' => $employees->map(function (Employee $employee) use ($requests, $types) {
                $employeeRequests = $requests->get($employee->id, collect());

                return [
                    ...$this->employeeColumns($employee),
                    ...$types->mapWithKeys(fn ($type) => ['type_'.$type->id => (float) $employeeRequests->where('leave_type_id', $type->id)->sum('days')])->all(),
                    'total' => (float) $employeeRequests->sum('days'),
                ];
            })->filter(fn (array $row) => $row['total'] > 0)->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return Collection<int, Collection<int, AttendanceDay>>
     */
    private function days(Collection $employees, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return AttendanceDay::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->between($from, $to)
            ->get(['employee_id', 'date', 'shift_id', 'day_type', 'status', 'worked_minutes', 'late_minutes', 'early_leave_minutes', 'overtime_minutes', 'approved_overtime_minutes', 'is_missing_checkout'])
            ->groupBy('employee_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function employeeColumns(Employee $employee): array
    {
        return ['code' => $employee->employee_code, 'name' => $employee->name, 'department' => $employee->department?->name];
    }

    /**
     * @param  array<string, string>  $columns
     * @return array<int, array{key: string, label: string}>
     */
    private function columns(array $columns): array
    {
        return collect(['code' => 'Code', 'name' => 'Employee', 'department' => 'Department', ...$columns])
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])
            ->values()
            ->all();
    }
}
