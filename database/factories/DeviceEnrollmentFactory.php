<?php

namespace Database\Factories;

use App\Enums\EnrollmentStatus;
use App\Models\Device;
use App\Models\DeviceEnrollment;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceEnrollment>
 */
class DeviceEnrollmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'device_id' => Device::factory(),
            'status' => EnrollmentStatus::OnDevice,
            'synced_at' => now(),
        ];
    }
}
