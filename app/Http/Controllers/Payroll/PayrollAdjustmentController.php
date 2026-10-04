<?php

namespace App\Http\Controllers\Payroll;

use App\Concerns\FlashesToast;
use App\Enums\AdjustmentKind;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Services\Payroll\AdjustmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bonuses, allowances, commission, arrears, fines and other one-off deductions per month.
 */
class PayrollAdjustmentController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsView->value), 403);

        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $user = $request->user();
        $month = CarbonImmutable::parse(($request->input('month') ?? now()->format('Y-m')).'-01');

        $adjustments = PayrollAdjustment::query()
            ->whereDate('period', $month->toDateString())
            ->whereHas('employee', fn ($query) => $query->visibleTo($user)->search($request->string('search')->toString() ?: null))
            ->when($request->input('kind'), fn ($query, $kind) => $query->where('kind', $kind))
            ->with(['employee:id,name,employee_code', 'payrollRun:id,reference'])
            ->latest()
            ->get();

        return Inertia::render('payroll/adjustments', [
            'month' => $month->format('Y-m'),
            'adjustments' => $adjustments,
            'totals' => [
                'earnings' => (string) $adjustments->filter(fn ($adjustment) => $adjustment->kind->isEarning())->sum('amount'),
                'deductions' => (string) $adjustments->reject(fn ($adjustment) => $adjustment->kind->isEarning())->sum('amount'),
            ],
            'kinds' => AdjustmentKind::options(),
            'employees' => fn () => Employee::query()->visibleTo($user)->active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'departments' => Department::query()->active()->orderBy('name')->get(['id', 'name']),
            'canManage' => $user->can(PermissionEnum::AdjustmentsManage->value),
        ]);
    }

    /**
     * Add an adjustment for one or more employees, as a fixed amount or a percentage of salary.
     */
    public function store(Request $request, AdjustmentService $service): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsManage->value), 403);

        $data = $request->validate([
            'kind' => ['required', Rule::enum(AdjustmentKind::class)],
            'name' => ['required', 'string', 'max:100'],
            'period' => ['required', 'date_format:Y-m'],
            'amount_type' => ['required', Rule::in(['fixed', 'percent'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'percent_of' => ['required_if:amount_type,percent', 'nullable', Rule::in(['basic', 'gross'])],
            'notes' => ['nullable', 'string', 'max:500'],
            'scope' => ['required', Rule::in(['employees', 'group'])],
            'employee_ids' => ['required_if:scope,employees', 'nullable', 'array'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'branch_id' => ['nullable', 'integer', Rule::exists('branches', 'id')],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')],
        ]);

        if ($data['scope'] === 'group') {
            $data['employee_ids'] = null;
        }

        $count = $service->bulk([...$data, 'period' => $data['period'].'-01'], $request->user());

        $count > 0
            ? $this->flashSuccess($count === 1 ? 'Adjustment added.' : "Adjustment added for {$count} employees.")
            : $this->flashWarning('No employees matched, or their amounts came to zero.');

        return back();
    }

    public function destroy(Request $request, PayrollAdjustment $payrollAdjustment): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsManage->value), 403);
        $this->authorize('view', $payrollAdjustment->employee);

        if ($payrollAdjustment->payroll_run_id) {
            $this->flashError('This was paid in an approved payroll and cannot be removed.');
        } else {
            $payrollAdjustment->delete();
            $this->flashSuccess('Adjustment removed. Regenerate any draft payroll for that month.');
        }

        return back();
    }
}
