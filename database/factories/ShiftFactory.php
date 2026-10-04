<?php

namespace Database\Factories;

use App\Models\Shift;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Shift>
 */
class ShiftFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Day',
            'start_time' => '09:00',
            'end_time' => '17:00',
            'break_minutes' => 0,
            'color' => 'sky',
            'is_active' => true,
        ];
    }

    public function between(string $start, string $end, string $name = 'Shift'): static
    {
        return $this->state(fn () => ['name' => $name, 'start_time' => $start, 'end_time' => $end]);
    }

    public function night(): static
    {
        return $this->between('22:00', '06:00', 'Night');
    }
}
