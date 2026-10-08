<?php

namespace App\Services\Trading\Indicators;

final class Ema
{
    /**
     * Ported from backtester `calculateEMA` to keep behavior identical.
     *
     * @param array<int, float> $values
     * @return array<int, float>
     */
    public static function calculate(array $values, int $period): array
    {
        if ($values === []) {
            return [];
        }

        if (count($values) < $period) {
            return array_fill(0, count($values), NAN);
        }

        $multiplier = 2 / ($period + 1);
        $ema = [];

        $seed = array_slice($values, 0, $period);
        $prevEma = array_sum($seed) / $period;

        foreach ($values as $i => $value) {
            if ($i < $period) {
                $ema[] = NAN;
                continue;
            }

            $prevEma = ($value - $prevEma) * $multiplier + $prevEma;
            $ema[] = $prevEma;
        }

        return $ema;
    }
}

