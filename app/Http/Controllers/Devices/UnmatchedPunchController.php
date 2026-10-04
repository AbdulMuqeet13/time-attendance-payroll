<?php

namespace App\Http\Controllers\Devices;

use App\Concerns\FlashesToast;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scans whose device PIN matches no employee. Giving an employee that PIN attaches the scans to them.
 */
class UnmatchedPunchController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::DevicesView->value), 403);

        $user = $request->user();

        $pins = AttendancePunch::query()
            ->unmatched()
            ->valid()
            ->when($user->branch_id, fn ($query, $branchId) => $query->whereHas('device', fn ($query) => $query->where('branch_id', $branchId)))
            ->selectRaw('pin, count(*) as punches_count, min(punched_at) as first_punch_at, max(punched_at) as last_punch_at')
            ->groupBy('pin')
            ->orderByDesc('last_punch_at')
            ->get();

        return Inertia::render('devices/unmatched-punches', [
            'pins' => $pins,
            'employees' => Employee::query()->visibleTo($user)->active()->whereNull('device_pin')->orderBy('name')
                ->get(['id', 'name', 'employee_code']),
            'canAssign' => $user->can(PermissionEnum::EmployeesUpdate->value),
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::EmployeesUpdate->value), 403);

        $data = $request->validate([
            'pin' => ['required', 'string', 'max:14', Rule::unique('employees', 'device_pin')],
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('device_pin')->whereNull('deleted_at')],
        ]);

        $employee = Employee::query()->visibleTo($request->user())->findOrFail((int) $data['employee_id']);
        $employee->update(['device_pin' => $data['pin']]);

        $this->flashSuccess("PIN {$data['pin']} given to {$employee->name}. Their earlier scans were added to attendance.");

        return back();
    }
}
