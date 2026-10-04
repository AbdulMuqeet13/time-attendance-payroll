<?php

namespace Database\Factories;

use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Payroll records are produced by PayrollService; build them through it in tests.
 *
 * @extends Factory<PayrollRun>
 */
class PayrollRunFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [];
    }
}
