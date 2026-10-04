<?php

namespace Database\Factories;

use App\Enums\PunchSource;
use App\Models\AttendancePunch;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AttendancePunch>
 */
class AttendancePunchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'pin' => null,
            'punched_at' => '2026-10-05 09:00:00',
            'source' => PunchSource::Device,
            'verify_type' => 1,
            'dedupe_hash' => sha1((string) Str::uuid()),
        ];
    }

    public function at(string $punchedAt): static
    {
        return $this->state(fn () => ['punched_at' => $punchedAt]);
    }
}
