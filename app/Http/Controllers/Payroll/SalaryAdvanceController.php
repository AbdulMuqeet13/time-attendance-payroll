<?php

namespace App\Http\Controllers\Payroll;

use App\Concerns\FlashesToast;
use App\Enums\AdvanceStatus;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SalaryAdvance;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Salary advances and loans, recovered in monthly installments by payroll.
 */
class SalaryAdvanceController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsView->value), 403);

        $user = $request->user();

        $advances = SalaryAdvance::query()
            ->whereHas('employee', fn ($query) => $query->visibleTo($user)->search($request->string('search')->toString() ?: null))
            ->when($request->input('status', 'active'), fn ($query, $status) => $status === 'all' ? $query : $query->where('status', $status))
            ->with(['employee:id,name,employee_code', 'recoveries' => fn ($query) => $query->with('payslip.payrollRun:id,reference,status')->latest('recovered_on')])
            ->latest('issued_on')
            ->get()
            ->map(fn (SalaryAdvance $advance) => [...$advance->toArray(), 'remaining' => $advance->remaining()]);

        return Inertia::render('payroll/advances', [
            'advances' => $advances,
            'employees' => fn () => Employee::query()->visibleTo($user)->active()->orderBy('name')->get(['id', 'name', 'employee_code']),
            'statuses' => AdvanceStatus::options(),
            'canManage' => $user->can(PermissionEnum::AdjustmentsManage->value),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsManage->value), 403);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'issued_on' => ['required', 'date'],
            'installment_amount' => ['required', 'numeric', 'gt:0', 'lte:amount'],
            'start_period' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $employee = Employee::query()->visibleTo($request->user())->findOrFail((int) $data['employee_id']);

        SalaryAdvance::query()->create([
            ...$data,
            'employee_id' => $employee->id,
            'start_period' => $data['start_period'].'-01',
            'status' => AdvanceStatus::Active,
            'created_by' => $request->user()->id,
        ]);

        $this->flashSuccess('Advance recorded. Installments are taken from payroll starting that month.');

        return back();
    }

    /**
     * Record a repayment made outside payroll (e.g. cash).
     */
    public function repay(Request $request, SalaryAdvance $salaryAdvance): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsManage->value), 403);
        $this->authorize('view', $salaryAdvance->employee);

        $remaining = $salaryAdvance->remaining();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:'.$remaining],
            'recovered_on' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $salaryAdvance->recoveries()->create($data);

        if (! Money::isPositive($salaryAdvance->remaining())) {
            $salaryAdvance->update(['status' => AdvanceStatus::Settled]);
        }

        $this->flashSuccess('Repayment recorded.');

        return back();
    }

    public function cancel(Request $request, SalaryAdvance $salaryAdvance): RedirectResponse
    {
        abort_unless($request->user()->can(PermissionEnum::AdjustmentsManage->value), 403);
        $this->authorize('view', $salaryAdvance->employee);

        $salaryAdvance->update(['status' => AdvanceStatus::Cancelled]);
        $this->flashSuccess('Advance cancelled. No further installments will be taken.');

        return back();
    }
}
