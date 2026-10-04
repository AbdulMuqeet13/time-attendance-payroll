<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use App\Models\Concerns\SerializesLocalDates;
use App\Support\Money;
use Database\Factories\SalaryAdvanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $amount
 * @property Carbon $issued_on
 * @property string $installment_amount
 * @property Carbon $start_period First month an installment is taken
 * @property AdvanceStatus $status
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Collection<int, AdvanceRecovery> $recoveries
 */
#[Fillable(['employee_id', 'amount', 'issued_on', 'installment_amount', 'start_period', 'status', 'notes', 'created_by'])]
class SalaryAdvance extends Model
{
    /** @use HasFactory<SalaryAdvanceFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'issued_on' => 'date:Y-m-d',
            'start_period' => 'date:Y-m-d',
            'status' => AdvanceStatus::class,
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
     * @return HasMany<AdvanceRecovery, $this>
     */
    public function recoveries(): HasMany
    {
        return $this->hasMany(AdvanceRecovery::class);
    }

    /**
     * The amount still to recover. Recoveries from draft payslips are excluded until the run is approved.
     */
    public function remaining(?int $ignorePayrollRunId = null): string
    {
        $recovered = (string) $this->recoveries()
            ->where(fn ($query) => $query->whereNull('payslip_id')->orWhereHas('payslip.payrollRun', fn ($query) => $query
                ->whereIn('status', ['approved', 'paid'])
                ->when($ignorePayrollRunId, fn ($query, int $id) => $query->whereKeyNot($id))))
            ->sum('amount');

        return Money::max('0', Money::sub((string) $this->amount, $recovered));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
