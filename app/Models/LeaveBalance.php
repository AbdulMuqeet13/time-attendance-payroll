<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $leave_type_id
 * @property int $year
 * @property string $entitled
 * @property string $carried_forward
 * @property string $adjustment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read LeaveType $leaveType
 */
#[Fillable(['employee_id', 'leave_type_id', 'year', 'entitled', 'carried_forward', 'adjustment'])]
class LeaveBalance extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'entitled' => 'decimal:1',
            'carried_forward' => 'decimal:1',
            'adjustment' => 'decimal:1',
        ];
    }

    /**
     * Days available this year before any are used.
     */
    public function total(): float
    {
        return (float) $this->entitled + (float) $this->carried_forward + (float) $this->adjustment;
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<LeaveType, $this>
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class)->withTrashed();
    }
}
