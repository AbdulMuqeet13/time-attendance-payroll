<?php

namespace App\Models;

use App\Enums\BiometricType;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\BiometricTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A biometric template read from a device, used to put the same employee on other devices.
 *
 * "storage" records which device table it came from: "fingertmp" (classic fingerprint table) or "biodata"
 * (unified table for faces, palms and newer fingerprint formats). It is written back the same way.
 *
 * @property int $id
 * @property int $employee_id
 * @property BiometricType $type
 * @property int $finger_index
 * @property string $template Base64 template (encrypted at rest)
 * @property int|null $size
 * @property string $valid_flag
 * @property string $storage fingertmp|biodata
 * @property int|null $biodata_type
 * @property string|null $major_version
 * @property string|null $minor_version
 * @property string|null $format
 * @property int|null $source_device_id
 * @property Carbon $captured_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Employee $employee
 * @property-read Device|null $sourceDevice
 */
#[Fillable([
    'employee_id', 'type', 'finger_index', 'template', 'size', 'valid_flag', 'storage', 'biodata_type',
    'major_version', 'minor_version', 'format', 'source_device_id', 'captured_at',
])]
#[Hidden(['template'])]
class BiometricTemplate extends Model
{
    /** @use HasFactory<BiometricTemplateFactory> */
    use HasFactory, SerializesLocalDates;

    public const STORAGE_FINGERTMP = 'fingertmp';

    public const STORAGE_BIODATA = 'biodata';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BiometricType::class,
            'template' => 'encrypted',
            'finger_index' => 'integer',
            'size' => 'integer',
            'biodata_type' => 'integer',
            'captured_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }
}
