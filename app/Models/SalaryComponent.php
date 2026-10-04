<?php

namespace App\Models;

use App\Enums\SalaryComponentType;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\SalaryComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A part of a salary breakdown. Earnings add up to the gross salary; deductions (e.g. income tax) are
 * taken off every month.
 *
 * @property int $id
 * @property string $name
 * @property SalaryComponentType $type
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable(['name', 'type', 'sort_order', 'is_active'])]
class SalaryComponent extends Model
{
    /** @use HasFactory<SalaryComponentFactory> */
    use HasFactory, LogsActivity, SerializesLocalDates, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SalaryComponentType::class,
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<SalaryComponent>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<SalaryComponent>  $query
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
