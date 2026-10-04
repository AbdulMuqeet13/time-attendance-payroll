<?php

namespace App\Http\Controllers\Payroll;

use App\Concerns\FlashesToast;
use App\Enums\PaymentMethod;
use App\Enums\PermissionEnum;
use App\Enums\RoleEnum;
use App\Exceptions\Payroll\PayrollException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Payroll\StorePayrollRunRequest;
use App\Models\Branch;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollRunController extends Controller
{
    use FlashesToast;

    public function index(Request $request): Response
    {
        $this->ensure($request->user(), PermissionEnum::PayrollView);

        $user = $request->user();

        return Inertia::render('payroll/index', [
            'runs' => PayrollRun::query()
                ->when($user->branch_id, fn ($query, $branchId) => $query->where('branch_id', $branchId))
                ->with(['branch:id,name', 'creator:id,name', 'approver:id,name'])
                ->latest('period_start')
                ->latest('id')
                ->paginate(20),
            'branches' => Branch::query()->active()
                ->when($user->branch_id, fn ($query, $branchId) => $query->whereKey($branchId))
                ->orderBy('name')->get(['id', 'name']),
            'defaultPeriod' => [
                'start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                'end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],
            'canRun' => $user->can(PermissionEnum::PayrollRun->value),
        ]);
    }

    public function store(StorePayrollRunRequest $request, PayrollService $payroll): RedirectResponse
    {
        try {
            ['run' => $run, 'skipped' => $skipped] = $payroll->generate(
                CarbonImmutable::parse($request->validated('period_start')),
                CarbonImmutable::parse($request->validated('period_end')),
                $request->validated('branch_id'),
                $request->user(),
                $request->validated('notes'),
            );
        } catch (PayrollException $exception) {
            $this->flashError($exception->getMessage());

            return back();
        }

        $skipped === []
            ? $this->flashSuccess("Draft {$run->reference} created with {$run->employee_count} payslips.")
            : $this->flashWarning("Draft {$run->reference} created. Skipped without a salary: ".implode(', ', $skipped).'.');

        return to_route('payroll.show', $run);
    }

    public function show(Request $request, PayrollRun $payrollRun): Response
    {
        $this->ensureCanSee($request->user(), $payrollRun);

        $payslips = $payrollRun->payslips()
            ->when($request->input('search'), fn ($query, $search) => $query->where(fn ($query) => $query
                ->where('employee_name', 'like', "%{$search}%")->orWhere('employee_code', 'like', "%{$search}%")))
            ->with('items')
            ->orderBy('employee_code')
            ->get();

        $user = $request->user();

        return Inertia::render('payroll/show', [
            'run' => $payrollRun->load(['branch:id,name', 'creator:id,name', 'approver:id,name']),
            'payslips' => $payslips,
            'summary' => [
                'shortfalls' => $payslips->filter(fn (Payslip $payslip) => (float) $payslip->shortfall > 0)->count(),
                'unpaid' => $payslips->where('payment_status.value', 'unpaid')->count(),
                'bank' => (string) $payslips->where('payment_method', PaymentMethod::Bank)->sum('net_pay'),
                'cash' => (string) $payslips->where('payment_method', PaymentMethod::Cash)->sum('net_pay'),
            ],
            'can' => [
                'run' => $user->can(PermissionEnum::PayrollRun->value),
                'approve' => $user->can(PermissionEnum::PayrollApprove->value),
                'pay' => $user->can(PermissionEnum::PayrollPay->value),
                'revert' => $user->hasRole(RoleEnum::SuperAdmin->value),
            ],
        ]);
    }

    public function regenerate(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->ensure($request->user(), PermissionEnum::PayrollRun);
        $this->ensureCanSee($request->user(), $payrollRun);

        return $this->attempt(function () use ($payroll, $payrollRun) {
            $skipped = $payroll->regenerate($payrollRun);

            return $skipped === [] ? 'Payslips regenerated.' : 'Payslips regenerated. Skipped without a salary: '.implode(', ', $skipped).'.';
        });
    }

    public function approve(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->ensure($request->user(), PermissionEnum::PayrollApprove);
        $this->ensureCanSee($request->user(), $payrollRun);

        return $this->attempt(function () use ($payroll, $payrollRun, $request) {
            $payroll->approve($payrollRun, $request->user());

            return 'Payroll approved. Attendance for the period is now locked.';
        });
    }

    public function revert(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        abort_unless($request->user()->hasRole(RoleEnum::SuperAdmin->value), 403);

        return $this->attempt(function () use ($payroll, $payrollRun) {
            $payroll->revertToDraft($payrollRun);

            return 'Payroll is a draft again and attendance is unlocked.';
        });
    }

    public function markPaid(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->ensure($request->user(), PermissionEnum::PayrollPay);
        $this->ensureCanSee($request->user(), $payrollRun);

        $data = $request->validate([
            'payslip_ids' => ['nullable', 'array'],
            'payslip_ids.*' => ['integer'],
            'paid_on' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->attempt(function () use ($payroll, $payrollRun, $data) {
            $count = $payroll->markPaid($payrollRun, $data['payslip_ids'] ?? null, CarbonImmutable::parse($data['paid_on']), $data['reference'] ?? null);

            return "{$count} payslip(s) marked as paid.";
        });
    }

    public function cancel(Request $request, PayrollRun $payrollRun, PayrollService $payroll): RedirectResponse
    {
        $this->ensure($request->user(), PermissionEnum::PayrollRun);
        $this->ensureCanSee($request->user(), $payrollRun);

        return $this->attempt(function () use ($payroll, $payrollRun) {
            $payroll->cancel($payrollRun);

            return 'Payroll run cancelled.';
        });
    }

    public function payslipPdf(Request $request, PayrollRun $payrollRun, Payslip $payslip, Settings $settings): HttpResponse
    {
        $this->ensureCanSee($request->user(), $payrollRun);

        $payslip->load('items');

        return Pdf::loadView('pdf.payslip', [
            'run' => $payrollRun,
            'payslip' => $payslip,
            'company' => ['name' => $settings->companyName(), 'address' => $settings->get('company.address'), 'phone' => $settings->get('company.phone')],
        ])->download('payslip-'.Str::slug($payslip->employee_name).'-'.$payrollRun->period_start->format('Y-m').'.pdf');
    }

    /**
     * Bank transfer sheet: one row per employee paid by bank.
     */
    public function bankSheet(Request $request, PayrollRun $payrollRun): BinaryFileResponse
    {
        $this->ensureCanSee($request->user(), $payrollRun);

        $path = storage_path('app/private/exports/bank-'.$payrollRun->reference.'-'.Str::random(6).'.xlsx');
        @mkdir(dirname($path), 0755, true);

        $writer = SimpleExcelWriter::create($path);
        $payrollRun->payslips()->where('payment_method', PaymentMethod::Bank)->orderBy('bank_name')->orderBy('employee_code')
            ->each(fn (Payslip $payslip) => $writer->addRow([
                'Employee Code' => $payslip->employee_code,
                'Employee Name' => $payslip->employee_name,
                'Bank' => $payslip->bank_name,
                'Account Title' => $payslip->account_title ?? $payslip->employee_name,
                'Account / IBAN' => $payslip->account_number,
                'Amount' => (float) $payslip->net_pay,
                'Reference' => $payrollRun->reference,
            ]));
        $writer->close();

        return response()->download($path, "bank-transfer-{$payrollRun->reference}.xlsx")->deleteFileAfterSend();
    }

    /**
     * Payroll register: every payslip with its attendance counts and totals.
     */
    public function register(Request $request, PayrollRun $payrollRun): BinaryFileResponse
    {
        $this->ensureCanSee($request->user(), $payrollRun);

        $path = storage_path('app/private/exports/register-'.$payrollRun->reference.'-'.Str::random(6).'.xlsx');
        @mkdir(dirname($path), 0755, true);

        $writer = SimpleExcelWriter::create($path);
        $payrollRun->payslips()->orderBy('employee_code')->each(fn (Payslip $payslip) => $writer->addRow([
            'Code' => $payslip->employee_code,
            'Name' => $payslip->employee_name,
            'Department' => $payslip->department,
            'Designation' => $payslip->designation,
            'Branch' => $payslip->branch,
            'Monthly Gross' => (float) $payslip->monthly_gross,
            'Days Employed' => $payslip->employed_days,
            'Present' => (float) $payslip->present_days,
            'Absent' => (float) $payslip->absent_days,
            'Half Days' => (float) $payslip->half_days,
            'Paid Leave' => (float) $payslip->paid_leave_days,
            'Unpaid Leave' => (float) $payslip->unpaid_leave_days,
            'Lates' => $payslip->late_count,
            'Overtime Hours' => round(($payslip->overtime_minutes + $payslip->holiday_overtime_minutes) / 60, 2),
            'Earnings' => (float) $payslip->earnings_total,
            'Deductions' => (float) $payslip->deductions_total,
            'Net Pay' => (float) $payslip->net_pay,
            'Payment' => $payslip->payment_method->label(),
            'Status' => $payslip->payment_status->label(),
        ]));
        $writer->close();

        return response()->download($path, "payroll-register-{$payrollRun->reference}.xlsx")->deleteFileAfterSend();
    }

    private function ensure(User $user, PermissionEnum $permission): void
    {
        abort_unless($user->can($permission->value), 403);
    }

    private function ensureCanSee(User $user, PayrollRun $run): void
    {
        $this->ensure($user, PermissionEnum::PayrollView);
        abort_if($user->branch_id && $run->branch_id !== $user->branch_id, 403);
    }

    /**
     * @param  callable(): string  $action
     */
    private function attempt(callable $action): RedirectResponse
    {
        try {
            $this->flashSuccess($action());
        } catch (PayrollException $exception) {
            $this->flashError($exception->getMessage());
        }

        return back();
    }
}
