<?php

namespace App\Http\Controllers\Attendance;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    /**
     * Daily attendance: every employee's rows for one date with sessions and flags.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceDay::class);

        $request->validate([
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
        ]);

        $user = $request->user();
        $date = CarbonImmutable::parse($request->input('date', today()->toDateString()));

        $days = AttendanceDay::query()
            ->whereDate('date', $date->toDateString())
            ->whereHas('employee', fn ($query) => $query->visibleTo($user)
                ->search($request->string('search')->toString() ?: null)
                ->when($request->input('department_id'), fn ($query, $id) => $query->where('department_id', $id)))
            ->when($request->input('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->with(['employee:id,name,employee_code,department_id,branch_id', 'employee.department:id,name', 'shift', 'sessions', 'leaveRequest.leaveType:id,name'])
            ->get();

        $summary = $days->groupBy(fn (AttendanceDay $day) => $day->status->value)->map->count();

        $filtered = $days
            ->when($request->input('status'), fn ($collection, $status) => $collection->filter(fn (AttendanceDay $day) => $day->status->value === $status))
            ->sortBy(fn (AttendanceDay $day) => [$day->employee->name, $day->scheduled_start?->toDateTimeString() ?? ''])
            ->values();

        return Inertia::render('attendance/index', [
            'date' => $date->toDateString(),
            'days' => $filtered,
            'summary' => $summary,
            'filters' => $request->only(['search', 'branch_id', 'department_id', 'status']),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'statuses' => AttendanceStatus::options(),
            'employees' => fn () => Employee::query()->visibleTo($user)->active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'canManage' => $user->can('attendance.manage'),
        ]);
    }

    /**
     * Monthly register: employees × days, one status per day (the worst when a day has several shifts).
     */
    public function register(Request $request): Response
    {
        $this->authorize('viewAny', AttendanceDay::class);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);

        $user = $request->user();
        $month = CarbonImmutable::parse(($request->input('month') ?? now()->format('Y-m')).'-01');
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        $employees = Employee::query()
            ->visibleTo($user)
            ->employedBetween($from, $to)
            ->search($request->string('search')->toString() ?: null)
            ->when($request->input('branch_id'), fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($request->input('department_id'), fn ($query, $id) => $query->where('department_id', $id))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25), ['id', 'name', 'employee_code', 'joining_date', 'exit_date'])
            ->withQueryString();

        $days = AttendanceDay::query()
            ->whereIn('employee_id', $employees->pluck('id'))
            ->between($from, $to)
            ->get(['employee_id', 'date', 'status', 'late_minutes', 'worked_minutes', 'overtime_minutes', 'approved_overtime_minutes', 'is_missing_checkout', 'leave_fraction'])
            ->groupBy('employee_id');

        $rows = $employees->getCollection()->map(function (Employee $employee) use ($days) {
            $employeeDays = $days->get($employee->id, collect());
            $byDate = $employeeDays->groupBy(fn (AttendanceDay $day) => $day->date->toDateString());

            $cells = $byDate->map(fn ($rows) => [
                'status' => $rows->sortByDesc(fn (AttendanceDay $day) => $day->status->severity())->first()->status->value,
                'missing_checkout' => $rows->contains('is_missing_checkout', true),
            ]);

            $statuses = $cells->pluck('status')->countBy();

            return [
                'employee' => $employee->only(['id', 'name', 'employee_code']),
                'cells' => $cells,
                'totals' => [
                    'present' => ($statuses['present'] ?? 0) + ($statuses['late'] ?? 0),
                    'late' => $employeeDays->where('status', AttendanceStatus::Late)->count(),
                    'half_day' => $statuses['half_day'] ?? 0,
                    'absent' => $statuses['absent'] ?? 0,
                    'leave' => $statuses['leave'] ?? 0,
                    'worked_minutes' => $employeeDays->sum('worked_minutes'),
                    'overtime_minutes' => $employeeDays->sum(fn (AttendanceDay $day) => $day->approved_overtime_minutes ?? 0),
                    'pending_overtime_minutes' => $employeeDays->whereNull('approved_overtime_minutes')->sum('overtime_minutes'),
                ],
            ];
        });

        return Inertia::render('attendance/register', [
            'month' => $month->format('Y-m'),
            'dates' => collect(CarbonPeriod::create($from, $to))->map(fn ($date) => [
                'date' => $date->toDateString(),
                'day' => $date->format('j'),
                'weekday' => $date->format('D'),
            ]),
            'rows' => $rows,
            'pagination' => collect($employees->toArray())->except('data'),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Every punch of one employee on one date (device, manual and voided), for the day detail drawer.
     */
    public function punches(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('view', $employee);
        abort_unless($request->user()->can('attendance.view'), 403);

        $date = CarbonImmutable::parse($request->validate(['date' => ['required', 'date']])['date']);

        return response()->json([
            'punches' => AttendancePunch::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('punched_at', [$date->startOfDay()->subHours(6), $date->endOfDay()->addHours(12)])
                ->with(['device:id,name', 'creator:id,name'])
                ->orderBy('punched_at')
                ->get(['id', 'device_id', 'punched_at', 'verify_type', 'source', 'reason', 'created_by', 'voided_at', 'void_reason']),
        ]);
    }
}
