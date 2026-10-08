<?php

namespace App\Services\Trading\Indicators;

final class Rsi
{
    /**
     * @param array<int, float> $values
     * @return array<int, float>
     */
    public static function calculate(array $values, int $period = 14): array
    {
        if ($values === []) {
            return [];
        }

        if ($period <= 1) {
            return array_fill(0, count($values), NAN);
        }

        $rsi = array_fill(0, count($values), NAN);
        if (count($values) <= $period) {
            return $rsi;
        }

        $gains = 0.0;
        $losses = 0.0;
        for ($i = 1; $i <= $period; $i += 1) {
            $delta = (float) $values[$i] - (float) $values[$i - 1];
            if ($delta >= 0) {
                $gains += $delta;
            } else {
                $losses -= $delta;
            }
        }

        $avgGain = $gains / $period;
        $avgLoss = $losses / $period;

        $firstRs = $avgLoss == 0.0 ? INF : ($avgGain / $avgLoss);
        $rsi[$period] = 100 - (100 / (1 + $firstRs));

        for ($i = $period + 1; $i < count($values); $i += 1) {
            $delta = (float) $values[$i] - (float) $values[$i - 1];
            $gain = $delta > 0 ? $delta : 0.0;
            $loss = $delta < 0 ? -$delta : 0.0;

            $avgGain = (($avgGain * ($period - 1)) + $gain) / $period;
            $avgLoss = (($avgLoss * ($period - 1)) + $loss) / $period;

            $rs = $avgLoss == 0.0 ? INF : ($avgGain / $avgLoss);
            $rsi[$i] = 100 - (100 / (1 + $rs));
        }

        return $rsi;
    }
}
