<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\DeviceEnrollmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property int $device_id
 * @property EnrollmentStatus $status
 * @property string|null $batch_id
 * @property string|null $message
 * @property Carbon|null $synced_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Device $device
 */
#[Fillable(['employee_id', 'device_id', 'status', 'batch_id', 'message', 'synced_at'])]
class DeviceEnrollment extends Model
{
    /** @use HasFactory<DeviceEnrollmentFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'synced_at' => 'datetime',
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
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
