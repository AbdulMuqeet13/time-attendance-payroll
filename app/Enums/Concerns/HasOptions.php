<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;

/**
 * Helpers for string-backed enums: raw values for validation and value/label pairs for selects.
 */
trait HasOptions
{
    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }

    public function label(): string
    {
        return Str::headline($this->value);
    }
}
