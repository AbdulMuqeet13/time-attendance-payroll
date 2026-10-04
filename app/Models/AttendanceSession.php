<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $attendance_day_id
 * @property Carbon $check_in
 * @property Carbon|null $check_out
 * @property int|null $in_punch_id
 * @property int|null $out_punch_id
 * @property int $minutes
 * @property-read AttendanceDay $attendanceDay
 */
#[Fillable(['attendance_day_id', 'check_in', 'check_out', 'in_punch_id', 'out_punch_id', 'minutes'])]
class AttendanceSession extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AttendanceDay, $this>
     */
    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(AttendanceDay::class);
    }
}
