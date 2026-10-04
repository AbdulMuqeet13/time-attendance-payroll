<?php

namespace Database\Factories;

use App\Enums\DeviceCommandStatus;
use App\Enums\DeviceCommandType;
use App\Models\Device;
use App\Models\DeviceCommand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceCommand>
 */
class DeviceCommandFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'device_id' => Device::factory(),
            'sequence' => fake()->unique()->numberBetween(1, 1_000_000),
            'type' => DeviceCommandType::Info,
            'command' => 'INFO',
            'status' => DeviceCommandStatus::Pending,
        ];
    }

    public function sent(?string $at = null): static
    {
        return $this->state(fn () => ['status' => DeviceCommandStatus::Sent, 'sent_at' => $at ?? now(), 'attempts' => 1]);
    }
}
