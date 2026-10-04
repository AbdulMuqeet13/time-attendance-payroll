<?php

namespace App\Support;

/**
 * Decimal money arithmetic with bcmath. Amounts are numeric strings; never floats.
 */
final class Money
{
    private const SCALE = 6;

    /**
     * @param  numeric-string  ...$amounts
     * @return numeric-string
     */
    public static function add(string ...$amounts): string
    {
        return array_reduce($amounts, fn (string $total, string $amount) => bcadd($total, $amount, self::SCALE), '0');
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function sub(string $a, string $b): string
    {
        return bcsub($a, $b, self::SCALE);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string|int|float  $b
     * @return numeric-string
     */
    public static function mul(string $a, string|int|float $b): string
    {
        return bcmul($a, self::normalise($b), self::SCALE);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string|int|float  $b
     * @return numeric-string
     */
    public static function div(string $a, string|int|float $b): string
    {
        $divisor = self::normalise($b);

        return bccomp($divisor, '0', self::SCALE) === 0 ? '0' : bcdiv($a, $divisor, self::SCALE);
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function min(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) <= 0 ? $a : $b;
    }

    /**
     * @param  numeric-string  $a
     * @param  numeric-string  $b
     * @return numeric-string
     */
    public static function max(string $a, string $b): string
    {
        return bccomp($a, $b, self::SCALE) >= 0 ? $a : $b;
    }

    /**
     * @param  numeric-string  $amount
     */
    public static function isPositive(string $amount): bool
    {
        return bccomp($amount, '0', self::SCALE) === 1;
    }

    /**
     * @param  numeric-string  $amount
     */
    public static function isNegative(string $amount): bool
    {
        return bccomp($amount, '0', self::SCALE) === -1;
    }

    /**
     * Round half up to the given decimals.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    public static function round(string $amount, int $decimals = 2): string
    {
        $offset = bcdiv('5', bcpow('10', (string) ($decimals + 1)), $decimals + 1);
        $rounded = self::isNegative($amount) ? bcsub($amount, $offset, $decimals) : bcadd($amount, $offset, $decimals);

        return $rounded === '-0.00' ? '0.00' : $rounded;
    }

    /**
     * @param  numeric-string|int|float  $value
     * @return numeric-string
     */
    private static function normalise(string|int|float $value): string
    {
        return is_float($value) ? rtrim(rtrim(number_format($value, self::SCALE, '.', ''), '0'), '.') ?: '0' : (string) $value;
    }
}
