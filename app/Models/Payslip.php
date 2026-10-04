<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\PayslipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property int|null $employee_salary_id
 * @property string $employee_code
 * @property string $employee_name
 * @property string|null $department
 * @property string|null $designation
 * @property string|null $branch
 * @property PaymentMethod $payment_method
 * @property string|null $bank_name
 * @property string|null $account_title
 * @property string|null $account_number
 * @property string $monthly_gross
 * @property string $day_divisor
 * @property string $per_day_rate
 * @property string $per_hour_rate
 * @property int $period_days
 * @property int $employed_days
 * @property int $scheduled_days
 * @property string $present_days
 * @property string $absent_days
 * @property string $half_days
 * @property string $paid_leave_days
 * @property string $unpaid_leave_days
 * @property int $holidays
 * @property int $weekly_offs
 * @property int $late_count
 * @property int $late_minutes
 * @property int $short_minutes
 * @property int $overtime_minutes
 * @property int $holiday_overtime_minutes
 * @property int $worked_minutes
 * @property string $earnings_total
 * @property string $deductions_total
 * @property string $net_pay
 * @property string $shortfall
 * @property PaymentStatus $payment_status
 * @property Carbon|null $paid_at
 * @property string|null $payment_reference
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PayrollRun $payrollRun
 * @property-read Employee $employee
 * @property-read Collection<int, PayslipItem> $items
 */
#[Fillable([
    'payroll_run_id', 'employee_id', 'employee_salary_id', 'employee_code', 'employee_name', 'department', 'designation',
    'branch', 'payment_method', 'bank_name', 'account_title', 'account_number', 'monthly_gross', 'day_divisor',
    'per_day_rate', 'per_hour_rate', 'period_days', 'employed_days', 'scheduled_days', 'present_days', 'absent_days',
    'half_days', 'paid_leave_days', 'unpaid_leave_days', 'holidays', 'weekly_offs', 'late_count', 'late_minutes',
    'short_minutes', 'overtime_minutes', 'holiday_overtime_minutes', 'worked_minutes', 'earnings_total',
    'deductions_total', 'net_pay', 'shortfall', 'payment_status', 'paid_at', 'payment_reference', 'notes',
])]
class Payslip extends Model
{
    /** @use HasFactory<PayslipFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'monthly_gross' => 'decimal:2',
            'day_divisor' => 'decimal:2',
            'per_day_rate' => 'decimal:4',
            'per_hour_rate' => 'decimal:4',
            'present_days' => 'decimal:1',
            'absent_days' => 'decimal:1',
            'half_days' => 'decimal:1',
            'paid_leave_days' => 'decimal:1',
            'unpaid_leave_days' => 'decimal:1',
            'earnings_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'shortfall' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return HasMany<PayslipItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayslipItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
