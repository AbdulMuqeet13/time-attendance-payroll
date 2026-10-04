<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'serial_number' => strtoupper(fake()->unique()->bothify('???#########')),
            'name' => 'Main Gate',
            'branch_id' => Branch::factory(),
            'is_active' => true,
            'push_version' => '2.4.1',
            'auto_backup' => 'none',
            'backup_retention' => 5,
        ];
    }

    /**
     * Registered itself but no admin has assigned it to a branch yet.
     */
    public function unclaimed(): static
    {
        return $this->state(fn () => ['branch_id' => null]);
    }

    public function online(): static
    {
        return $this->state(fn () => ['last_seen_at' => now()]);
    }
}
