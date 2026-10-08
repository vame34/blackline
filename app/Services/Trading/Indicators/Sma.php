<?php

namespace App\Services\Trading\Indicators;

final class Sma
{
    /**
     * @param array<int, float> $values
     * @return array<int, float>
     */
    public static function calculate(array $values, int $period): array
    {
        $result = [];
        $sum = 0.0;

        foreach ($values as $i => $value) {
            $sum += $value;

            if ($i >= $period) {
                $sum -= $values[$i - $period];
            }

            if ($i < $period - 1) {
                $result[] = NAN;
                continue;
            }

            $result[] = $sum / $period;
        }

        return $result;
    }
}

