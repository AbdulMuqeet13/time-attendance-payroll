<?php

namespace App\Models;

use App\Models\Concerns\SerializesLocalDates;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A user record as the device last reported it.
 *
 * @property int $id
 * @property int $device_id
 * @property string $pin
 * @property string|null $name
 * @property int $privilege
 * @property string|null $card
 * @property bool $has_password
 * @property int $fingerprint_count
 * @property bool $has_face
 * @property Carbon $seen_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Device $device
 */
#[Fillable(['device_id', 'pin', 'name', 'privilege', 'card', 'has_password', 'fingerprint_count', 'has_face', 'seen_at'])]
class DeviceUser extends Model
{
    use SerializesLocalDates;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'privilege' => 'integer',
            'has_password' => 'boolean',
            'fingerprint_count' => 'integer',
            'has_face' => 'boolean',
            'seen_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
