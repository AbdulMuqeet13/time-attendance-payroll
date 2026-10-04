<?php

namespace App\Http\Controllers\Leaves;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\Leaves\LeaveBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveBalanceController extends Controller
{
    use FlashesToast;

    public function index(Request $request, LeaveBalanceService $balances): Response
    {
        abort_unless($request->user()->can(PermissionEnum::LeavesView->value), 403);

        $year = $request->integer('year', now()->year);
        $types = LeaveType::query()->active()->orderBy('name')->get()->filter->hasQuota()->values();

        $employees = Employee::query()
            ->visibleTo($request->user())
            ->active()
            ->search($request->string('search')->toString() ?: null)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25), ['id', 'name', 'employee_code', 'joining_date', 'gender'])
            ->withQueryString();

        $rows = $employees->getCollection()->map(fn (Employee $employee) => [
            'employee' => $employee->only(['id', 'name', 'employee_code']),
            'balances' => $types->mapWithKeys(fn (LeaveType $type) => [$type->id => [
                ...$balances->summary($employee, $type, $year),
                'balance_id' => $balances->balanceFor($employee, $type, $year)->id,
            ]]),
        ]);

        return Inertia::render('leaves/balances', [
            'year' => $year,
            'types' => $types->map->only(['id', 'name', 'code']),
            'rows' => $rows,
            'pagination' => collect($employees->toArray())->except('data'),
            'canAdjust' => $request->user()->can(PermissionEnum::LeavesManage->value),
        ]);
    }

    /**
     * Add or remove days by hand (e.g. compensation for working a holiday).
     */
    public function adjust(Request $request, LeaveBalance $leaveBalance): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::LeavesManage->value), 403);
        $this->authorize('update', $leaveBalance->employee);

        $data = $request->validate(['adjustment' => ['required', 'numeric', 'between:-365,365', 'multiple_of:0.5']]);
        $leaveBalance->update($data);

        $this->flashSuccess('Balance adjusted.');

        return back();
    }
}
