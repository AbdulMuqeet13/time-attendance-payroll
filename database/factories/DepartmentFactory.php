<?php

namespace Database\Factories;

use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Operations', 'Finance', 'Human Resources', 'Sales', 'IT', 'Production', 'Quality', 'Logistics', 'Admin', 'Security']).' '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
