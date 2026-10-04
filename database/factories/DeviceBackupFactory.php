<?php

namespace Database\Factories;

use App\Models\DeviceBackup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Backups are produced by DeviceBackupService; build them through it in tests.
 *
 * @extends Factory<DeviceBackup>
 */
class DeviceBackupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
