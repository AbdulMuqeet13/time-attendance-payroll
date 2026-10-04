<?php

namespace App\Http\Controllers\SelfService;

use App\Enums\PayrollStatus;
use App\Enums\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Models\AttendanceDay;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Payslip;
use App\Services\Leaves\LeaveBalanceService;
use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Employee self-service: own attendance, leave and payslips.
 */
class MyPortalController extends Controller
{
    public function index(Request $request, LeaveBalanceService $balances): Response
    {
        $employee = $this->employee($request);
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = CarbonImmutable::parse(($request->input('month') ?? now()->format('Y-m')).'-01');

        if (! $employee) {
            return Inertia::render('self-service/index', ['employee' => null]);
        }

        $year = now()->year;

        return Inertia::render('self-service/index', [
            'employee' => $employee->load(['branch:id,name', 'department:id,name', 'designation:id,name'])
                ->only(['id', 'name', 'employee_code', 'device_pin', 'joining_date', 'branch', 'department', 'designation']),
            'month' => $month->format('Y-m'),
            'days' => AttendanceDay::query()
                ->where('employee_id', $employee->id)
                ->between($month->startOfMonth(), $month->endOfMonth())
                ->with('shift:id,name,start_time,end_time')
                ->orderBy('date')
                ->get(['id', 'date', 'shift_id', 'day_type', 'status', 'first_in', 'last_out', 'worked_minutes', 'late_minutes', 'overtime_minutes', 'approved_overtime_minutes', 'is_missing_checkout']),
            'balances' => LeaveType::query()->active()->get()->filter->hasQuota()
                ->filter(fn (LeaveType $type) => $type->gender === null || $type->gender === $employee->gender)
                ->map(fn (LeaveType $type) => ['type' => $type->only(['id', 'name']), ...$balances->summary($employee, $type, $year)])
                ->values(),
            'leaveRequests' => $employee->leaveRequests()->with('leaveType:id,name,is_paid')->latest('start_date')->limit(20)->get(),
            'leaveTypes' => LeaveType::query()->active()
                ->where(fn ($query) => $query->whereNull('gender')->orWhere('gender', $employee->gender))
                ->orderBy('name')->get(['id', 'name', 'code', 'is_paid', 'allow_half_day', 'requires_attachment']),
            'payslips' => Payslip::query()
                ->where('employee_id', $employee->id)
                ->whereHas('payrollRun', fn ($query) => $query->whereIn('status', [PayrollStatus::Approved, PayrollStatus::Paid]))
                ->with('payrollRun:id,reference,period_start,period_end')
                ->latest('id')
                ->limit(24)
                ->get(['id', 'payroll_run_id', 'net_pay', 'earnings_total', 'deductions_total', 'payment_status', 'paid_at']),
        ]);
    }

    public function payslip(Request $request, Payslip $payslip, Settings $settings): HttpResponse
    {
        $employee = $this->employee($request);
        $run = $payslip->payrollRun;

        abort_unless($employee && $payslip->employee_id === $employee->id && in_array($run->status, [PayrollStatus::Approved, PayrollStatus::Paid], true), 404);

        return Pdf::loadView('pdf.payslip', [
            'run' => $run,
            'payslip' => $payslip->load('items'),
            'company' => ['name' => $settings->companyName(), 'address' => $settings->get('company.address'), 'phone' => $settings->get('company.phone')],
        ])->download('payslip-'.Str::slug($payslip->employee_name).'-'.$run->period_start->format('Y-m').'.pdf');
    }

    private function employee(Request $request): ?Employee
    {
        abort_unless($request->user()->can(PermissionEnum::SelfServiceAccess->value), 403);

        return Employee::query()->where('user_id', $request->user()->id)->first();
    }
}
