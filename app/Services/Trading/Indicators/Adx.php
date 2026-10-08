<?php

namespace App\Services\Trading\Indicators;

use App\DTOs\Trading\Candle;

final class Adx
{
    /**
     * @param array<int, Candle> $candles
     * @return array<int, array{adx: float, plusDI: float, minusDI: float}>
     */
    public static function calculate(array $candles, int $period = 14): array
    {
        if ($candles === []) {
            return [];
        }

        $p = max(2, $period);
        $len = count($candles);
        $result = array_fill(0, $len, ['adx' => NAN, 'plusDI' => NAN, 'minusDI' => NAN]);
        if ($len <= $p) {
            return $result;
        }

        $tr = array_fill(0, $len, 0.0);
        $plusDm = array_fill(0, $len, 0.0);
        $minusDm = array_fill(0, $len, 0.0);
        $dx = array_fill(0, $len, NAN);

        for ($i = 1; $i < $len; $i += 1) {
            $prev = $candles[$i - 1];
            $curr = $candles[$i];
            $upMove = $curr->high - $prev->high;
            $downMove = $prev->low - $curr->low;
            $plusDm[$i] = $upMove > $downMove && $upMove > 0 ? $upMove : 0.0;
            $minusDm[$i] = $downMove > $upMove && $downMove > 0 ? $downMove : 0.0;
            $highLow = $curr->high - $curr->low;
            $highClose = abs($curr->high - $prev->close);
            $lowClose = abs($curr->low - $prev->close);
            $tr[$i] = max($highLow, $highClose, $lowClose);
        }

        $smoothTr = 0.0;
        $smoothPlusDm = 0.0;
        $smoothMinusDm = 0.0;
        for ($i = 1; $i <= $p; $i += 1) {
            $smoothTr += $tr[$i];
            $smoothPlusDm += $plusDm[$i];
            $smoothMinusDm += $minusDm[$i];
        }

        $firstAdxIndex = (2 * $p) - 1;
        $dxSum = 0.0;
        for ($i = $p; $i < $len; $i += 1) {
            if ($i > $p) {
                $smoothTr = $smoothTr - ($smoothTr / $p) + $tr[$i];
                $smoothPlusDm = $smoothPlusDm - ($smoothPlusDm / $p) + $plusDm[$i];
                $smoothMinusDm = $smoothMinusDm - ($smoothMinusDm / $p) + $minusDm[$i];
            }

            $plus = $smoothTr > 0 ? 100 * ($smoothPlusDm / $smoothTr) : 0.0;
            $minus = $smoothTr > 0 ? 100 * ($smoothMinusDm / $smoothTr) : 0.0;
            $result[$i]['plusDI'] = $plus;
            $result[$i]['minusDI'] = $minus;

            $diSum = $plus + $minus;
            $dx[$i] = $diSum > 0 ? 100 * (abs($plus - $minus) / $diSum) : 0.0;

            if ($i < $firstAdxIndex) {
                $dxSum += $dx[$i];
                continue;
            }

            if ($i === $firstAdxIndex) {
                $dxSum += $dx[$i];
                $result[$i]['adx'] = $dxSum / $p;
                continue;
            }

            $prevAdx = $result[$i - 1]['adx'];
            $result[$i]['adx'] = (($prevAdx * ($p - 1)) + $dx[$i]) / $p;
        }

        return $result;
    }
}
