<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\PayrollRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
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
 * @property string $reference
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int|null $branch_id null = every branch
 * @property PayrollStatus $status
 * @property int $employee_count
 * @property string $earnings_total
 * @property string $deductions_total
 * @property string $net_total
 * @property string|null $notes
 * @property array<string, mixed>|null $settings_snapshot
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch|null $branch
 * @property-read Collection<int, Payslip> $payslips
 * @property-read User|null $creator
 * @property-read User|null $approver
 */
#[Fillable([
    'reference', 'period_start', 'period_end', 'branch_id', 'status', 'employee_count', 'earnings_total',
    'deductions_total', 'net_total', 'notes', 'settings_snapshot', 'created_by', 'approved_by', 'approved_at',
    'paid_at', 'cancelled_at',
])]
class PayrollRun extends Model
{
    /** @use HasFactory<PayrollRunFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date:Y-m-d',
            'period_end' => 'date:Y-m-d',
            'status' => PayrollStatus::class,
            'earnings_total' => 'decimal:2',
            'deductions_total' => 'decimal:2',
            'net_total' => 'decimal:2',
            'settings_snapshot' => 'array',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return HasMany<Payslip, $this>
     */
    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isDraft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /**
     * Runs that would pay the same employees for overlapping days: same branch, or either covers every branch.
     * Cancelled runs don't count.
     *
     * @param  Builder<PayrollRun>  $query
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $from, CarbonInterface $to, ?int $branchId): void
    {
        $query->where('status', '!=', PayrollStatus::Cancelled)
            ->where('period_start', '<=', $to->toDateString())
            ->where('period_end', '>=', $from->toDateString())
            ->when($branchId, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'net_total', 'notes'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
