<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveStatus;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\AttendancePunch;
use App\Models\Device;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($this->isSelfServiceOnly($user)) {
            return to_route('my.index');
        }

        $today = CarbonImmutable::today();
        $visibleEmployees = fn ($query) => $query->visibleTo($user);
        $canAttendance = $user->can(PermissionEnum::AttendanceView->value);

        return Inertia::render('dashboard', [
            'today' => $canAttendance ? $this->todayCounts($user, $today) : null,
            'trend' => $canAttendance ? Inertia::defer(fn () => $this->trend($user, $today)) : null,
            'devices' => $user->can(PermissionEnum::DevicesView->value) ? [
                'online' => Device::query()->visibleTo($user)->claimed()->where('last_seen_at', '>=', now()->subSeconds(Device::ONLINE_SECONDS))->count(),
                'total' => Device::query()->visibleTo($user)->claimed()->count(),
                'unclaimed' => $user->branch_id ? 0 : Device::query()->whereNull('branch_id')->count(),
                'unmatched_scans' => AttendancePunch::query()->unmatched()->valid()->count(),
            ] : null,
            'pending' => [
                'leave' => $user->can(PermissionEnum::LeavesApprove->value)
                    ? LeaveRequest::query()->where('status', LeaveStatus::Pending)->whereHas('employee', $visibleEmployees)->count() : null,
                'overtime' => $user->can(PermissionEnum::OvertimeApprove->value)
                    ? AttendanceDay::query()->overtimePending()->whereNull('locked_at')->whereHas('employee', $visibleEmployees)->count() : null,
                'missing_checkouts' => $canAttendance
                    ? AttendanceDay::query()->where('is_missing_checkout', true)->where('date', '>=', $today->subDays(7)->toDateString())->whereHas('employee', $visibleEmployees)->count() : null,
                'without_roster' => $user->can(PermissionEnum::ShiftsView->value)
                    ? Employee::query()->visibleTo($user)->active()->whereDoesntHave('shiftAssignments', fn ($query) => $query->effectiveBetween($today, $today))->count() : null,
            ],
            'payroll' => $user->can(PermissionEnum::PayrollView->value)
                ? PayrollRun::query()->when($user->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))->latest('period_start')->first(['id', 'reference', 'period_start', 'period_end', 'status', 'net_total', 'employee_count'])
                : null,
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function todayCounts(User $user, CarbonImmutable $today): array
    {
        $statuses = AttendanceDay::query()
            ->whereDate('date', $today->toDateString())
            ->whereHas('employee', fn ($query) => $query->visibleTo($user))
            ->get(['employee_id', 'status'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->sortByDesc(fn (AttendanceDay $day) => match ($day->status) {
                AttendanceStatus::Late => 5, AttendanceStatus::Present => 4, AttendanceStatus::HalfDay => 3,
                AttendanceStatus::Leave => 2, AttendanceStatus::Absent => 1, default => 0,
            })->first()->status->value)
            ->countBy();

        return [
            'employees' => Employee::query()->visibleTo($user)->active()->count(),
            'in' => ($statuses['present'] ?? 0) + ($statuses['late'] ?? 0) + ($statuses['half_day'] ?? 0),
            'late' => $statuses['late'] ?? 0,
            'absent' => $statuses['absent'] ?? 0,
            'leave' => $statuses['leave'] ?? 0,
            'not_in_yet' => $statuses['scheduled'] ?? 0,
            'off' => ($statuses['weekly_off'] ?? 0) + ($statuses['holiday'] ?? 0),
        ];
    }

    /**
     * @return array<int, array{date: string, present: int, late: int, absent: int, leave: int}>
     */
    private function trend(User $user, CarbonImmutable $today): array
    {
        $from = $today->subDays(29);
        $counts = AttendanceDay::query()
            ->between($from, $today)
            ->whereNotNull('shift_id')
            ->whereHas('employee', fn ($query) => $query->visibleTo($user))
            ->toBase()
            ->selectRaw('date, status, count(distinct employee_id) as total')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn ($row) => CarbonImmutable::parse($row->date)->toDateString());

        return collect(CarbonPeriod::create($from, $today))->map(function ($date) use ($counts) {
            $day = $counts->get($date->toDateString(), collect())->pluck('total', 'status');

            return [
                'date' => $date->toDateString(),
                'present' => (int) ($day[AttendanceStatus::Present->value] ?? 0),
                'late' => (int) ($day[AttendanceStatus::Late->value] ?? 0),
                'absent' => (int) ($day[AttendanceStatus::Absent->value] ?? 0),
                'leave' => (int) ($day[AttendanceStatus::Leave->value] ?? 0),
            ];
        })->all();
    }

    /**
     * Employees with a login only have self-service access; they land on their own portal.
     */
    private function isSelfServiceOnly(User $user): bool
    {
        return $user->can(PermissionEnum::SelfServiceAccess->value)
            && $user->getAllPermissions()->pluck('name')->diff([PermissionEnum::SelfServiceAccess->value])->isEmpty()
            && ! $user->hasRole('Super Admin');
    }
}
