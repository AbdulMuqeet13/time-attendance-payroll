<?php

namespace App\Models;

use App\Enums\PayslipItemCategory;
use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $payslip_id
 * @property string $side earning|deduction
 * @property PayslipItemCategory $category
 * @property string $name
 * @property string|null $quantity
 * @property string|null $unit
 * @property string|null $rate
 * @property string $amount
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $sort_order
 */
#[Fillable(['payslip_id', 'side', 'category', 'name', 'quantity', 'unit', 'rate', 'amount', 'source_type', 'source_id', 'sort_order'])]
class PayslipItem extends Model
{
    use SerializesLocalDates;

    public const EARNING = 'earning';

    public const DEDUCTION = 'deduction';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => PayslipItemCategory::class,
            'quantity' => 'decimal:2',
            'rate' => 'decimal:4',
            'amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
