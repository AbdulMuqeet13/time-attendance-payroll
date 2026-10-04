<?php

namespace App\Models;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Models\Concerns\SerializesLocalDates;
use Database\Factories\DeviceCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property string|null $batch_id
 * @property int $sequence
 * @property DeviceCommandType $type
 * @property string $command
 * @property DeviceCommandStatus $status
 * @property int $attempts
 * @property Carbon|null $sent_at
 * @property Carbon|null $executed_at
 * @property int|null $return_code
 * @property string|null $response
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Device $device
 */
#[Fillable([
    'device_id', 'batch_id', 'sequence', 'type', 'command', 'status', 'attempts', 'sent_at', 'executed_at',
    'return_code', 'response', 'created_by',
])]
class DeviceCommand extends Model
{
    /** @use HasFactory<DeviceCommandFactory> */
    use HasFactory, SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DeviceCommandType::class,
            'status' => DeviceCommandStatus::class,
            'sequence' => 'integer',
            'attempts' => 'integer',
            'return_code' => 'integer',
            'sent_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Commands still waiting for a result (queued or delivered but not yet acknowledged).
     *
     * @param  Builder<DeviceCommand>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', [DeviceCommandStatus::Pending, DeviceCommandStatus::Sent]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [DeviceCommandStatus::Succeeded, DeviceCommandStatus::Failed, DeviceCommandStatus::Cancelled], true);
    }
}
