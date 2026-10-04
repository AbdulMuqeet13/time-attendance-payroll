<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Carbon\CarbonInterface;
use Database\Factories\HolidayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property Carbon $date
 * @property string $name
 * @property int|null $branch_id null = every branch
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Branch|null $branch
 */
#[Fillable(['date', 'name', 'branch_id'])]
class Holiday extends Model
{
    /** @use HasFactory<HolidayFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
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
     * Holidays that apply to the branch: its own plus the company-wide ones.
     *
     * @param  Builder<Holiday>  $query
     */
    public function scopeForBranch(Builder $query, int $branchId): void
    {
        $query->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId));
    }

    /**
     * @param  Builder<Holiday>  $query
     */
    public function scopeBetween(Builder $query, CarbonInterface $from, CarbonInterface $to): void
    {
        $query->whereBetween('date', [$from->toDateString(), $to->toDateString()]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
