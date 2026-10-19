<?php

namespace App\Support;

/**
 * Peso arithmetic in whole centavos.
 *
 * Money columns are DECIMAL(10,2). Doing +, - and comparisons on PHP floats
 * (and "within 0.004" tolerances) can drift by a centavo, so billing math
 * converts every amount to an integer number of centavos first, calculates
 * with integers, and only converts back for storage / display.
 */
final class Money
{
    /** "123.45" | 123.45 | null  ->  12345 */
    public static function cents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        return (int) round(((float) $amount) * 100);
    }

    /** 12345 -> 123.45 */
    public static function pesos(int $cents): float
    {
        return round($cents / 100, 2);
    }

    /**
     * $percent % of $cents, rounded half-up to a whole centavo and never
     * more than $cents itself.  percentOf(150000, 20) === 30000.
     */
    public static function percentOf(int $cents, float|int|string|null $percent): int
    {
        $hundredths = (int) round(((float) $percent) * 100);   // 20 % -> 2000

        if ($cents <= 0 || $hundredths <= 0) {
            return 0;
        }

        return min($cents, intdiv($cents * $hundredths + 5000, 10000));
    }
}
