<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\LeaveTypeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property bool $is_paid
 * @property string $yearly_quota
 * @property string $carry_forward_max
 * @property bool $allow_half_day
 * @property bool $requires_attachment
 * @property Gender|null $gender Only for this gender (e.g. maternity); null = everyone
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'code', 'is_paid', 'yearly_quota', 'carry_forward_max', 'allow_half_day', 'requires_attachment', 'gender', 'is_active'])]
class LeaveType extends Model
{
    /** @use HasFactory<LeaveTypeFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'yearly_quota' => 'decimal:1',
            'carry_forward_max' => 'decimal:1',
            'allow_half_day' => 'boolean',
            'requires_attachment' => 'boolean',
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Paid types with a yearly quota are tracked against a balance.
     */
    public function hasQuota(): bool
    {
        return $this->is_paid && (float) $this->yearly_quota > 0;
    }

    /**
     * @param  Builder<LeaveType>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
