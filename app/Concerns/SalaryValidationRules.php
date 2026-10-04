<?php

namespace App\Concerns;

use App\Enums\SalaryComponentType;
use App\Models\SalaryComponent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait SalaryValidationRules
{
    /**
     * Rules for a salary breakdown: an amount per salary component.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function salaryBreakdownRules(): array
    {
        return [
            'components' => ['required', 'array', 'min:1'],
            'components.*.salary_component_id' => ['required', 'integer', 'distinct', Rule::exists('salary_components', 'id')->whereNull('deleted_at')],
            'components.*.amount' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function salaryBreakdownAttributes(): array
    {
        return [
            'components.*.salary_component_id' => 'salary component',
            'components.*.amount' => 'component amount',
        ];
    }

    /**
     * Whether the earning components add up to more than zero.
     */
    protected function earningsTotalIsPositive(): bool
    {
        $earningIds = SalaryComponent::query()
            ->where('type', SalaryComponentType::Earning)
            ->pluck('id')
            ->all();

        $total = collect($this->input('components', []))
            ->filter(fn (mixed $component) => is_array($component) && in_array((int) ($component['salary_component_id'] ?? 0), $earningIds, true))
            ->sum(fn (array $component) => is_numeric($component['amount'] ?? null) ? (float) $component['amount'] : 0);

        return $total > 0;
    }
}
