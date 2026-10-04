<?php

namespace App\Http\Controllers\Shifts;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shifts\StoreShiftAssignmentRequest;
use App\Http\Requests\Shifts\UpdateShiftAssignmentRequest;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\RosterOverride;
use App\Models\Shift;
use App\Models\ShiftAssignment;
use App\Services\Attendance\AttendanceRebuilder;
use App\Services\ShiftAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The roster: who works which shift, plus one-day overrides.
 */
class ShiftAssignmentController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::ShiftsView->value), 403);

        $user = $request->user();
        $visibleEmployees = fn ($query) => $query->visibleTo($user);

        $assignments = ShiftAssignment::query()
            ->whereHas('employee', $visibleEmployees)
            ->with(['employee:id,name,employee_code,branch_id', 'employee.branch:id,name', 'shift'])
            ->when($request->input('shift_id'), fn ($query, $shiftId) => $query->where('shift_id', $shiftId))
            ->when($request->input('branch_id'), fn ($query, $branchId) => $query->whereHas('employee', fn ($query) => $query->where('branch_id', $branchId)))
            ->when($request->input('search'), fn ($query, $search) => $query->whereHas('employee', fn ($query) => $query->search($search)))
            ->when($request->input('scope', 'current') === 'current', fn ($query) => $query
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()->toDateString())))
            ->orderByDesc('effective_from')
            ->paginate($request->integer('per_page', 25))
            ->withQueryString();

        $overrides = RosterOverride::query()
            ->whereHas('employee', $visibleEmployees)
            ->with(['employee:id,name,employee_code', 'shift'])
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->orderBy('date')
            ->limit(200)
            ->get();

        return Inertia::render('shifts/roster', [
            'assignments' => $assignments,
            'overrides' => $overrides,
            'shifts' => Shift::query()->where('is_active', true)->orderBy('start_time')->get(['id', 'name', 'start_time', 'end_time']),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->visibleTo($user)->active()->orderBy('name')
                ->get(['id', 'name', 'employee_code', 'branch_id', 'department_id']),
            'canManage' => $user->can(PermissionEnum::ShiftsManage->value),
        ]);
    }

    public function store(StoreShiftAssignmentRequest $request, ShiftAssignmentService $service): RedirectResponse
    {
        $count = $service->assign($request->validated('employee_ids'), $request->validated(), $request->user());

        $this->flashSuccess($count === 1 ? 'Shift assigned.' : "Shift assigned to {$count} employees.");

        return back();
    }

    public function update(UpdateShiftAssignmentRequest $request, ShiftAssignment $shiftAssignment, ShiftAssignmentService $service): RedirectResponse
    {
        $service->update($shiftAssignment, $request->validated());

        $this->flashSuccess('Assignment updated.');

        return back();
    }

    public function destroy(Request $request, ShiftAssignment $shiftAssignment, AttendanceRebuilder $rebuilder): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can(PermissionEnum::ShiftsManage->value), 403);
        abort_if($user->branch_id && $shiftAssignment->employee->branch_id !== $user->branch_id, 403);

        $shiftAssignment->delete();
        $rebuilder->forEmployee($shiftAssignment->employee_id, $shiftAssignment->effective_from, $shiftAssignment->effective_to ?? now());

        $this->flashSuccess('Assignment removed. To keep past attendance accurate, prefer setting an end date.');

        return back();
    }
}
