<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $salary_advance_id
 * @property int|null $payslip_id null = repaid by hand
 * @property string $amount
 * @property Carbon $recovered_on
 * @property string|null $note
 * @property-read SalaryAdvance $salaryAdvance
 * @property-read Payslip|null $payslip
 */
#[Fillable(['salary_advance_id', 'payslip_id', 'amount', 'recovered_on', 'note'])]
class AdvanceRecovery extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'recovered_on' => 'date:Y-m-d',
        ];
    }

    /**
     * @return BelongsTo<SalaryAdvance, $this>
     */
    public function salaryAdvance(): BelongsTo
    {
        return $this->belongsTo(SalaryAdvance::class);
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
