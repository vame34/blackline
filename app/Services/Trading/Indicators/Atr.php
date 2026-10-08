<?php

namespace App\Services\Trading\Indicators;

use App\DTOs\Trading\Candle;

final class Atr
{
    /**
     * Ported from backtester `calculateATR` (note: intentionally keeps its off-by-one seeding).
     *
     * @param array<int, Candle> $candles
     * @return array<int, float>
     */
    public static function calculate(array $candles, int $period = 14): array
    {
        if ($candles === []) {
            return [];
        }

        $atr = [];
        $prevClose = $candles[0]->close;
        $sumTr = 0.0;

        foreach ($candles as $i => $candle) {
            $highLow = $candle->high - $candle->low;
            $highClose = abs($candle->high - $prevClose);
            $lowClose = abs($candle->low - $prevClose);
            $tr = max($highLow, $highClose, $lowClose);

            $prevClose = $candle->close;

            if ($i < $period) {
                $sumTr += $tr;
                $atr[] = NAN;
                continue;
            }

            if ($i === $period) {
                $sumTr += $tr;
                $atr[] = $sumTr / $period;
                continue;
            }

            $prevAtr = $atr[count($atr) - 1];
            $atr[] = (($prevAtr * ($period - 1)) + $tr) / $period;
        }

        return $atr;
    }
}

