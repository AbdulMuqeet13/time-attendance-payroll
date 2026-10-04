<?php

namespace App\Models;

use App\Enums\AdjustmentKind;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\PayrollAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A one-off bonus, allowance, commission, arrears, fine or deduction for a month.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $period First day of the month it is paid in
 * @property AdjustmentKind $kind
 * @property string $name
 * @property string $amount
 * @property string|null $notes
 * @property string|null $batch_id
 * @property int|null $payroll_run_id Set once an approved payroll has paid it
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read PayrollRun|null $payrollRun
 */
#[Fillable(['employee_id', 'period', 'kind', 'name', 'amount', 'notes', 'batch_id', 'payroll_run_id', 'created_by'])]
class PayrollAdjustment extends Model
{
    /** @use HasFactory<PayrollAdjustmentFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period' => 'date:Y-m-d',
            'kind' => AdjustmentKind::class,
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PayrollRun, $this>
     */
    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
