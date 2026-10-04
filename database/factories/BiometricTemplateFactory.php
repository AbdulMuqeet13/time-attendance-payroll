<?php

namespace Database\Factories;

use App\Enums\BiometricType;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BiometricTemplate>
 */
class BiometricTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $template = base64_encode(random_bytes(96));

        return [
            'employee_id' => Employee::factory(),
            'type' => BiometricType::Fingerprint,
            'finger_index' => 6,
            'template' => $template,
            'size' => strlen($template),
            'valid_flag' => '1',
            'storage' => BiometricTemplate::STORAGE_FINGERTMP,
            'major_version' => '10',
            'captured_at' => now(),
        ];
    }

    public function face(): static
    {
        return $this->state(fn () => [
            'type' => BiometricType::Face,
            'finger_index' => 0,
            'storage' => BiometricTemplate::STORAGE_BIODATA,
            'biodata_type' => 9,
            'major_version' => '39',
        ]);
    }
}
