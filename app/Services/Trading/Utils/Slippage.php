<?php

namespace App\Services\Trading\Utils;

final class Slippage
{
    public static function bpsToFraction(float|int|null $value): float
    {
        $parsed = (float) ($value ?? 0);
        if (!is_finite($parsed) || $parsed <= 0) {
            return 0.0;
        }
        return $parsed / 10_000;
    }

    public static function applyStopSlippage(float $price, string $direction, float|int|null $slippageBps): float
    {
        $base = (float) $price;
        if (!is_finite($base) || $base <= 0) {
            return $base;
        }

        $slip = self::bpsToFraction($slippageBps);
        if ($slip <= 0) {
            return $base;
        }

        $dir = strtoupper($direction);
        if ($dir === 'SHORT') {
            return $base * (1 + $slip);
        }

        return $base * (1 - $slip);
    }

    public static function applySpread(float $price, string $direction, string $side, float|int|null $spreadBps): float
    {
        $base = (float) $price;
        if (!is_finite($base) || $base <= 0) {
            return $base;
        }

        $spread = self::bpsToFraction($spreadBps);
        if ($spread <= 0) {
            return $base;
        }

        $dir = strtoupper($direction);
        $normalizedSide = strtolower($side);
        if (!in_array($normalizedSide, ['entry', 'exit'], true)) {
            return $base;
        }

        $sign = $normalizedSide === 'entry'
            ? ($dir === 'SHORT' ? -1 : 1)
            : ($dir === 'SHORT' ? 1 : -1);

        return $base * (1 + $sign * $spread);
    }

    public static function applyTradeSlippage(float $price, string $direction, string $side, float|int|null $slippageBps): float
    {
        $base = (float) $price;
        if (!is_finite($base) || $base <= 0) {
            return $base;
        }

        $slip = self::bpsToFraction($slippageBps);
        if ($slip <= 0) {
            return $base;
        }

        $dir = strtoupper($direction);
        $normalizedSide = strtolower($side);
        if (!in_array($normalizedSide, ['entry', 'exit'], true)) {
            return $base;
        }

        $sign = $normalizedSide === 'entry'
            ? ($dir === 'SHORT' ? -1 : 1)
            : ($dir === 'SHORT' ? 1 : -1);

        return $base * (1 + $sign * $slip);
    }

    /** Only an opening price beyond the stop is an adverse gap. */
    public static function adverseStopGapBps(float $open, float $stop, string $direction): float
    {
        if ($stop <= 0 || ! is_finite($open) || ! is_finite($stop)) {
            return 0.0;
        }

        $gap = strtoupper($direction) === 'SHORT' ? $open - $stop : $stop - $open;

        return max(0.0, $gap / $stop * 10_000);
    }

    public static function dynamicStopSlippageBps(
        float|int|null $baseBps,
        float|int|null $rangeBps,
        float|int|null $gapBps,
        bool $enabled = false,
        float|int|null $rangeTriggerBps = null,
        float|int|null $rangeCoeff = null,
        float|int|null $gapCoeff = null,
        float|int|null $capBps = null,
    ): float {
        $base = max(0.0, (float) ($baseBps ?? 0.0));
        if (! $enabled) {
            return $base;
        }

        $range = max(0.0, (float) ($rangeBps ?? 0.0));
        $gap = max(0.0, (float) ($gapBps ?? 0.0));
        $trigger = max(0.0, (float) ($rangeTriggerBps ?? 0.0));
        $rangeK = max(0.0, (float) ($rangeCoeff ?? 0.0));
        $gapK = max(0.0, (float) ($gapCoeff ?? 0.0));
        $cap = max($base, (float) ($capBps ?? $base));

        $rangeExtra = max(0.0, $range - $trigger) * $rangeK;
        $gapExtra = $gap * $gapK;
        $effective = $base + $rangeExtra + $gapExtra;

        return min($cap, max($base, $effective));
    }
}
