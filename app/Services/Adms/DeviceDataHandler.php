<?php

namespace App\Services\Adms;

use App\Events\DeviceDataReceived;
use App\Models\Device;
use App\Models\DeviceUser;
use Illuminate\Support\Collection;

/**
 * Handles user and biometric data uploaded by a device (OPERLOG, USERINFO, FINGERTMP, BIODATA, query results).
 */
class DeviceDataHandler
{
    public function __construct(private AdmsParser $parser) {}

    /**
     * @return int The number of records handled
     */
    public function handle(Device $device, string $table, string $body): int
    {
        $records = collect($this->parser->records($body, $table));

        if ($records->isEmpty()) {
            return 0;
        }

        $this->storeUsers($device, $records->where('type', 'user'));

        foreach ($records->groupBy('type') as $type => $group) {
            DeviceDataReceived::dispatch($device, (string) $type, $group->pluck('fields')->all());
        }

        return $records->count();
    }

    /**
     * @param  Collection<int, array{type: string, fields: array<string, string>}>  $users
     */
    private function storeUsers(Device $device, Collection $users): void
    {
        foreach ($users as $user) {
            $fields = $user['fields'];
            $pin = strtoupper(trim($fields['pin'] ?? ''));

            if ($pin === '') {
                continue;
            }

            DeviceUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'pin' => $pin],
                [
                    'name' => ($fields['name'] ?? '') !== '' ? $fields['name'] : null,
                    'privilege' => (int) ($fields['pri'] ?? $fields['privilege'] ?? 0),
                    'card' => ($fields['card'] ?? $fields['cardno'] ?? '') ?: null,
                    'has_password' => ($fields['passwd'] ?? $fields['password'] ?? '') !== '',
                    'seen_at' => now(),
                ],
            );
        }
    }
}
