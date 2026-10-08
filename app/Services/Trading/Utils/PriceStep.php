<?php

namespace App\Services\Trading\Utils;

final class PriceStep
{
    public static function normalizePriceStep(float|int|null $value): float
    {
        $step = (float) ($value ?? 0);
        if (!is_finite($step) || $step <= 0) {
            return 0.0;
        }
        return $step;
    }

    public static function roundToStep(float $price, float|int|null $step, string $mode = 'round'): float
    {
        $value = (float) $price;
        $tick = self::normalizePriceStep($step);
        if (!is_finite($value) || $tick <= 0) {
            return $value;
        }

        $scaled = $value / $tick;
        if (!is_finite($scaled)) {
            return $value;
        }

        return match ($mode) {
            'ceil' => ceil($scaled) * $tick,
            'floor' => floor($scaled) * $tick,
            default => round($scaled) * $tick,
        };
    }

    public static function applyEntryPriceStep(float $price, string $direction, float|int|null $step): float
    {
        $dir = strtoupper($direction);
        return self::roundToStep($price, $step, $dir === 'SHORT' ? 'floor' : 'ceil');
    }

    public static function applyStopPriceStep(float $price, string $direction, float|int|null $step): float
    {
        $dir = strtoupper($direction);
        return self::roundToStep($price, $step, $dir === 'SHORT' ? 'ceil' : 'floor');
    }

    public static function applyTakePriceStep(float $price, string $direction, float|int|null $step): float
    {
        $dir = strtoupper($direction);
        return self::roundToStep($price, $step, $dir === 'SHORT' ? 'ceil' : 'floor');
    }
}

