<?php

namespace App\Services\Trading\Strategies;

use App\DTOs\Trading\Candle;
use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\SpikeArea;
use App\DTOs\Trading\VolumeSignal;
use App\Services\Trading\Indicators\Adx;
use App\Services\Trading\Indicators\Atr;
use App\Services\Trading\Indicators\Ema;
use App\Services\Trading\Indicators\Rsi;
use App\Services\Trading\Indicators\Sma;
use App\Services\Trading\Utils\PriceStep;
use App\Services\Trading\Utils\Slippage;

final readonly class VolumeStrategy
{
    private const ALL_REGIMES = ['Trend', 'Expansion', 'Range', 'Compression'];

    private VolumeStrategyConfig $config;
    private VolumeSpikeDetector $spikeDetector;

    public function __construct(?VolumeStrategyConfig $config = null)
    {
        $this->config = $config ?? new VolumeStrategyConfig();
        $this->spikeDetector = new VolumeSpikeDetector(
            multiplier: $this->config->spikeMultiplier,
            window: $this->config->volumeWindow,
        );
    }

    /**
     * Generates entry signals (Entry/SL/TP) using the ported VolumeStrategy logic.
     *
     * @return array{signals: array<int, VolumeSignal>, debug?: array<string, int|float>}
     */
    public function generateEntrySignals(InstrumentData $instrumentData, bool $debug = false): array
    {
        $candles = $instrumentData->candles;
        $contextCandles = $instrumentData->contextCandles ?: $instrumentData->candles;

        $ignoreZero = $this->config->ignoreZeroVolumeCandles;
        $filteredCandles = $ignoreZero
            ? array_values(array_filter($candles, fn (Candle $c) => $c->volume > 0))
            : $candles;
        $filteredContextCandles = $ignoreZero
            ? array_values(array_filter($contextCandles, fn (Candle $c) => $c->volume > 0))
            : $contextCandles;

        $zeroVolumeFiltered = max(0, count($candles) - count($filteredCandles));
        $contextZeroVolumeFiltered = max(0, count($contextCandles) - count($filteredContextCandles));

        if ($filteredCandles === []) {
            return ['signals' => [], 'debug' => $debug ? $this->createEmptyDebug() : null];
        }

        $intervalMs = $this->resolveIntervalMs($instrumentData->interval, $filteredCandles);
        $closePrices = array_map(fn (Candle $c) => $c->close, $filteredCandles);
        $atr = Atr::calculate($filteredCandles, $this->config->atrPeriod);
        $volumeSeries = array_map(fn (Candle $c) => $c->volume, $filteredCandles);
        $volumeSma = Sma::calculate($volumeSeries, $this->config->volumeWindow);
        $volumeSmaFast = Sma::calculate($volumeSeries, $this->config->volumeFastPeriod);
        $volumeSmaSlow = Sma::calculate($volumeSeries, $this->config->volumeSlowPeriod);
        $spikes = $this->spikeDetector->detect($filteredCandles, $volumeSma);
        $entryEmaFast = Ema::calculate($closePrices, $this->config->entryFastPeriod);
        $entryEmaMid = Ema::calculate($closePrices, $this->config->entryMidPeriod);
        $entryEmaSlow = Ema::calculate($closePrices, $this->config->entrySlowPeriod);
        $rsi = Rsi::calculate($closePrices, $this->config->rsiPeriod);
        $adx = Adx::calculate($filteredCandles, $this->config->adxPeriod);

        $contextSeries = $filteredContextCandles !== [] ? $filteredContextCandles : $filteredCandles;
        $contextClose = array_map(fn (Candle $c) => $c->close, $contextSeries);
        $contextEmaFast = Ema::calculate($contextClose, $this->config->contextFastPeriod);
        $contextEmaSlow = Ema::calculate($contextClose, $this->config->contextSlowPeriod);
        $contextRsi = Rsi::calculate($contextClose, $this->config->contextRsiPeriod);
        $contextAdx = Adx::calculate($contextSeries, $this->config->contextAdxPeriod);
        $contextVolumeSma = $this->calculateContextVolumeSma($filteredContextCandles);

        $debugStats = $this->createEmptyDebug([
            'totalCandles' => count($filteredCandles),
            'spikes' => count($spikes),
            'zeroVolumeFiltered' => $zeroVolumeFiltered,
            'contextZeroVolumeFiltered' => $contextZeroVolumeFiltered,
        ]);

        $signals = [];
        $contextIndex = -1;
        $contextIntervalMs = $this->resolveContextIntervalMs($contextSeries);

        foreach ($filteredCandles as $i => $candle) {
            $atrValue = $atr[$i] ?? NAN;
            if (!is_finite($atrValue)) {
                $debugStats['atrUnavailable'] += 1;
                continue;
            }

            $entryFastValue = $entryEmaFast[$i] ?? NAN;
            $entryMidValue = $entryEmaMid[$i] ?? NAN;
            $entrySlowValue = $entryEmaSlow[$i] ?? NAN;
            if (!is_finite($entryFastValue) || !is_finite($entryMidValue) || !is_finite($entrySlowValue)) {
                $debugStats['entryUnavailable'] += 1;
                continue;
            }

            $contextIndex = $this->advanceClosedContextIndex(
                $contextSeries,
                $contextIndex,
                $candle->openTimeMs + $intervalMs,
                $contextIntervalMs,
            );

            $contextFast = $contextEmaFast[$contextIndex] ?? NAN;
            $contextSlow = $contextEmaSlow[$contextIndex] ?? NAN;
            if (!is_finite($contextFast) || !is_finite($contextSlow) || $contextSlow == 0.0) {
                $debugStats['contextUnavailable'] += 1;
                continue;
            }

            $fastDiff = ($contextFast - $contextSlow) / $contextSlow;
            if ($this->config->contextMinTrendStrength > 0 && abs($fastDiff) < $this->config->contextMinTrendStrength) {
                $debugStats['trendWeak'] += 1;
                continue;
            }

            $contextBull = $fastDiff > $this->config->contextTrendTolerance;
            $contextBear = $fastDiff < -$this->config->contextTrendTolerance;
            $trend = null;
            if ($contextBull && !$contextBear) {
                $trend = 'up';
            } elseif ($contextBear && !$contextBull) {
                $trend = 'down';
            } else {
                $debugStats['trendMismatch'] += 1;
                continue;
            }

            if (!$this->passesSmartFilters(
                trend: $trend,
                index: $i,
                contextIndex: $contextIndex,
                rsi: $rsi,
                adx: $adx,
                contextRsi: $contextRsi,
                contextAdx: $contextAdx,
                volumeSmaFast: $volumeSmaFast,
                volumeSmaSlow: $volumeSmaSlow,
                debugStats: $debugStats,
            )) {
                continue;
            }

            $area = $this->findArea($spikes, $i, $filteredCandles);
            if (!$area) {
                $debugStats['noArea'] += 1;
                continue;
            }

            $nearLevel = abs(($candle->hl2() - $area->level) / $area->level) <= $this->config->levelTolerance;
            $touchesLevel = $candle->low <= $area->level && $area->level <= $candle->high;
            if (!$nearLevel && !$touchesLevel) {
                $debugStats['levelMismatch'] += 1;
                continue;
            }

            $areaDirectionMatches = ($trend === 'up' && $area->direction === 'bullish')
                || ($trend === 'down' && $area->direction === 'bearish');
            if (!$areaDirectionMatches) {
                $debugStats['areaDirectionMismatch'] += 1;
                continue;
            }

            if (!$this->checkContextVolumeImpulse(
                contextCandles: $contextSeries,
                contextIndex: $contextIndex,
                contextVolumeSma: $contextVolumeSma,
                contextVolumeMultiplier: $instrumentData->contextVolumeMultiplier,
            )) {
                $debugStats['contextVolumeRejected'] += 1;
                continue;
            }

            $range = $candle->range();
            $bodyRatio = $range > 0 ? $candle->body() / $range : 0.0;
            $candleDirectionMatches = ($trend === 'up' && $candle->bodyDirection() === 'bullish')
                || ($trend === 'down' && $candle->bodyDirection() === 'bearish');
            if (!$candleDirectionMatches || $bodyRatio < $this->config->minBodyRatio) {
                $debugStats['candleRejected'] += 1;
                continue;
            }

            if (!$this->confirmVolume($candle, $area, $volumeSma[$i] ?? NAN)) {
                $debugStats['volumeRejected'] += 1;
                continue;
            }

            $touchesEntryMid = $candle->low <= $entryMidValue && $entryMidValue <= $candle->high;
            $touchesEntryFast = $candle->low <= $entryFastValue && $entryFastValue <= $candle->high;
            $touchesEntrySlow = $candle->low <= $entrySlowValue && $entrySlowValue <= $candle->high;
            $nearEntryMid = abs(($candle->hl2() - $entryMidValue) / $entryMidValue) <= $this->config->entryEmaTolerance;
            $nearEntryFast = abs(($candle->hl2() - $entryFastValue) / $entryFastValue) <= $this->config->entryEmaTolerance;
            $nearEntrySlow = abs(($candle->hl2() - $entrySlowValue) / $entrySlowValue) <= $this->config->entryEmaTolerance * 0.5;
            $entryTouchOk = $touchesEntryMid || $touchesEntryFast || $touchesEntrySlow
                || $nearEntryMid || $nearEntryFast || $nearEntrySlow;

            $entryTrendTolerance = $this->config->entryTrendTolerance ?: $this->config->entryEmaTolerance;
            $fastAboveMid = $entryFastValue >= $entryMidValue * (1 - $entryTrendTolerance);
            $midAboveSlow = $entryMidValue >= $entrySlowValue * (1 - $entryTrendTolerance);
            $fastBelowMid = $entryFastValue <= $entryMidValue * (1 + $entryTrendTolerance);
            $midBelowSlow = $entryMidValue <= $entrySlowValue * (1 + $entryTrendTolerance);
            $closeAboveSlow = $candle->close >= $entrySlowValue * (1 - $entryTrendTolerance);
            $closeBelowSlow = $candle->close <= $entrySlowValue * (1 + $entryTrendTolerance);

            $entryBull = $fastAboveMid && $midAboveSlow && $closeAboveSlow;
            $entryBear = $fastBelowMid && $midBelowSlow && $closeBelowSlow;

            if ($trend === 'up' && !$entryBull) {
                $debugStats['entryMismatch'] += 1;
                continue;
            }
            if ($trend === 'down' && !$entryBear) {
                $debugStats['entryMismatch'] += 1;
                continue;
            }
            if (!$entryTouchOk) {
                $debugStats['emaTouchMiss'] += 1;
                continue;
            }

            $direction = $trend === 'up' ? 'LONG' : 'SHORT';
            if ($direction === 'LONG' && !$this->config->allowLong) {
                $debugStats['directionBlocked'] += 1;
                continue;
            }
            if ($direction === 'SHORT' && !$this->config->allowShort) {
                $debugStats['directionBlocked'] += 1;
                continue;
            }

            $widgetMeta = [
                'contextFastSlowDiff' => $fastDiff,
                'entryFastSlope' => $this->calcSlope($entryEmaFast, $i, 10),
                'entryMidSlope' => $this->calcSlope($entryEmaMid, $i, 10),
                'entrySlowSlope' => $this->calcSlope($entryEmaSlow, $i, 10),
                'entryFastSlope3' => $this->calcSlope($entryEmaFast, $i, 3),
                'entryMidSlope3' => $this->calcSlope($entryEmaMid, $i, 3),
                'entrySlowSlope3' => $this->calcSlope($entryEmaSlow, $i, 3),
                'volumeRatio' => (is_finite($volumeSma[$i] ?? NAN) && ($volumeSma[$i] ?? 0.0) > 0)
                    ? ($candle->volume / $volumeSma[$i])
                    : 1.0,
                'volumeOverSma' => (is_finite($volumeSma[$i] ?? NAN) && ($volumeSma[$i] ?? 0.0) > 0)
                    ? ($candle->volume / $volumeSma[$i])
                    : 1.0,
                'rangeOverAtr' => $atrValue > 0 ? ($range / $atrValue) : 1.0,
                'atrPercent' => $candle->close > 0 ? (($atrValue / $candle->close) * 100.0) : 0.0,
                'bodyRatio' => $bodyRatio,
                'entrySlowDistPct' => $entrySlowValue > 0 ? (($candle->close - $entrySlowValue) / $entrySlowValue) : 0.0,
            ];

            $signal = $this->buildEntrySignal(
                direction: $direction,
                candleIndex: $i,
                candle: $candle,
                candles: $filteredCandles,
                atrValue: $atrValue,
                area: $area,
                spikes: $spikes,
                symbol: $instrumentData->symbol,
                exchange: $instrumentData->exchange,
                priceStep: $instrumentData->priceStep,
                intervalMs: $intervalMs,
                debugStats: $debugStats,
            );

            if ($signal) {
                $widgetMeta['stopDistancePct'] = $signal->entryPrice > 0
                    ? (abs($signal->entryPrice - $signal->stopLoss) / $signal->entryPrice)
                    : 0.0;
                if (!$this->passesWidgetFilter($signal->direction, $widgetMeta)) {
                    $debugStats['widgetRejected'] += 1;
                    continue;
                }
                $signals[] = $signal;
                $debugStats['tradesOpened'] += 1;
            }
        }

        return [
            'signals' => $signals,
            'debug' => $debug ? $debugStats : null,
        ];
    }

    /**
     * Generates full backtest signals (Entry/SL/TP + exit) using the ported logic.
     *
     * @return array{signals: array<int, VolumeSignal>, debug?: array<string, int|float>}
     */
    public function generateSignals(InstrumentData $instrumentData, bool $debug = false): array
    {
        $candles = $instrumentData->candles;
        $contextCandles = $instrumentData->contextCandles ?: $instrumentData->candles;

        $ignoreZero = $this->config->ignoreZeroVolumeCandles;
        $filteredCandles = $ignoreZero
            ? array_values(array_filter($candles, fn (Candle $c) => $c->volume > 0))
            : $candles;
        $filteredContextCandles = $ignoreZero
            ? array_values(array_filter($contextCandles, fn (Candle $c) => $c->volume > 0))
            : $contextCandles;

        $zeroVolumeFiltered = max(0, count($candles) - count($filteredCandles));
        $contextZeroVolumeFiltered = max(0, count($contextCandles) - count($filteredContextCandles));

        if ($filteredCandles === []) {
            return ['signals' => [], 'debug' => $debug ? $this->createEmptyDebug() : null];
        }

        $intervalMs = $this->resolveIntervalMs($instrumentData->interval, $filteredCandles);
        $closePrices = array_map(fn (Candle $c) => $c->close, $filteredCandles);
        $atr = Atr::calculate($filteredCandles, $this->config->atrPeriod);
        $volumeSeries = array_map(fn (Candle $c) => $c->volume, $filteredCandles);
        $volumeSma = Sma::calculate($volumeSeries, $this->config->volumeWindow);
        $volumeSmaFast = Sma::calculate($volumeSeries, $this->config->volumeFastPeriod);
        $volumeSmaSlow = Sma::calculate($volumeSeries, $this->config->volumeSlowPeriod);
        $spikes = $this->spikeDetector->detect($filteredCandles, $volumeSma);
        $entryEmaFast = Ema::calculate($closePrices, $this->config->entryFastPeriod);
        $entryEmaMid = Ema::calculate($closePrices, $this->config->entryMidPeriod);
        $entryEmaSlow = Ema::calculate($closePrices, $this->config->entrySlowPeriod);
        $rsi = Rsi::calculate($closePrices, $this->config->rsiPeriod);
        $adx = Adx::calculate($filteredCandles, $this->config->adxPeriod);

        $contextSeries = $filteredContextCandles !== [] ? $filteredContextCandles : $filteredCandles;
        $contextClose = array_map(fn (Candle $c) => $c->close, $contextSeries);
        $contextEmaFast = Ema::calculate($contextClose, $this->config->contextFastPeriod);
        $contextEmaSlow = Ema::calculate($contextClose, $this->config->contextSlowPeriod);
        $contextRsi = Rsi::calculate($contextClose, $this->config->contextRsiPeriod);
        $contextAdx = Adx::calculate($contextSeries, $this->config->contextAdxPeriod);
        $contextVolumeSma = $this->calculateContextVolumeSma($filteredContextCandles);

        $debugStats = $this->createEmptyDebug([
            'totalCandles' => count($filteredCandles),
            'spikes' => count($spikes),
            'zeroVolumeFiltered' => $zeroVolumeFiltered,
            'contextZeroVolumeFiltered' => $contextZeroVolumeFiltered,
        ]);

        $signals = [];
        $contextIndex = -1;
        $contextIntervalMs = $this->resolveContextIntervalMs($contextSeries);

        foreach ($filteredCandles as $i => $candle) {
            $atrValue = $atr[$i] ?? NAN;
            if (!is_finite($atrValue)) {
                $debugStats['atrUnavailable'] += 1;
                continue;
            }

            $entryFastValue = $entryEmaFast[$i] ?? NAN;
            $entryMidValue = $entryEmaMid[$i] ?? NAN;
            $entrySlowValue = $entryEmaSlow[$i] ?? NAN;
            if (!is_finite($entryFastValue) || !is_finite($entryMidValue) || !is_finite($entrySlowValue)) {
                $debugStats['entryUnavailable'] += 1;
                continue;
            }

            $contextIndex = $this->advanceClosedContextIndex(
                $contextSeries,
                $contextIndex,
                $candle->openTimeMs + $intervalMs,
                $contextIntervalMs,
            );

            $contextFast = $contextEmaFast[$contextIndex] ?? NAN;
            $contextSlow = $contextEmaSlow[$contextIndex] ?? NAN;
            if (!is_finite($contextFast) || !is_finite($contextSlow) || $contextSlow == 0.0) {
                $debugStats['contextUnavailable'] += 1;
                continue;
            }

            $fastDiff = ($contextFast - $contextSlow) / $contextSlow;
            if ($this->config->contextMinTrendStrength > 0 && abs($fastDiff) < $this->config->contextMinTrendStrength) {
                $debugStats['trendWeak'] += 1;
                continue;
            }

            $contextBull = $fastDiff > $this->config->contextTrendTolerance;
            $contextBear = $fastDiff < -$this->config->contextTrendTolerance;
            $trend = null;
            if ($contextBull && !$contextBear) {
                $trend = 'up';
            } elseif ($contextBear && !$contextBull) {
                $trend = 'down';
            } else {
                $debugStats['trendMismatch'] += 1;
                continue;
            }

            if (!$this->passesSmartFilters(
                trend: $trend,
                index: $i,
                contextIndex: $contextIndex,
                rsi: $rsi,
                adx: $adx,
                contextRsi: $contextRsi,
                contextAdx: $contextAdx,
                volumeSmaFast: $volumeSmaFast,
                volumeSmaSlow: $volumeSmaSlow,
                debugStats: $debugStats,
            )) {
                continue;
            }

            $area = $this->findArea($spikes, $i, $filteredCandles);
            if (!$area) {
                $debugStats['noArea'] += 1;
                continue;
            }

            $nearLevel = abs(($candle->hl2() - $area->level) / $area->level) <= $this->config->levelTolerance;
            $touchesLevel = $candle->low <= $area->level && $area->level <= $candle->high;
            if (!$nearLevel && !$touchesLevel) {
                $debugStats['levelMismatch'] += 1;
                continue;
            }

            $areaDirectionMatches = ($trend === 'up' && $area->direction === 'bullish')
                || ($trend === 'down' && $area->direction === 'bearish');
            if (!$areaDirectionMatches) {
                $debugStats['areaDirectionMismatch'] += 1;
                continue;
            }

            if (!$this->checkContextVolumeImpulse(
                contextCandles: $contextSeries,
                contextIndex: $contextIndex,
                contextVolumeSma: $contextVolumeSma,
                contextVolumeMultiplier: $instrumentData->contextVolumeMultiplier,
            )) {
                $debugStats['contextVolumeRejected'] += 1;
                continue;
            }

            $range = $candle->range();
            $bodyRatio = $range > 0 ? $candle->body() / $range : 0.0;
            $candleDirectionMatches = ($trend === 'up' && $candle->bodyDirection() === 'bullish')
                || ($trend === 'down' && $candle->bodyDirection() === 'bearish');
            if (!$candleDirectionMatches || $bodyRatio < $this->config->minBodyRatio) {
                $debugStats['candleRejected'] += 1;
                continue;
            }

            if (!$this->confirmVolume($candle, $area, $volumeSma[$i] ?? NAN)) {
                $debugStats['volumeRejected'] += 1;
                continue;
            }

            $touchesEntryMid = $candle->low <= $entryMidValue && $entryMidValue <= $candle->high;
            $touchesEntryFast = $candle->low <= $entryFastValue && $entryFastValue <= $candle->high;
            $touchesEntrySlow = $candle->low <= $entrySlowValue && $entrySlowValue <= $candle->high;
            $nearEntryMid = abs(($candle->hl2() - $entryMidValue) / $entryMidValue) <= $this->config->entryEmaTolerance;
            $nearEntryFast = abs(($candle->hl2() - $entryFastValue) / $entryFastValue) <= $this->config->entryEmaTolerance;
            $nearEntrySlow = abs(($candle->hl2() - $entrySlowValue) / $entrySlowValue) <= $this->config->entryEmaTolerance * 0.5;
            $entryTouchOk = $touchesEntryMid || $touchesEntryFast || $touchesEntrySlow
                || $nearEntryMid || $nearEntryFast || $nearEntrySlow;

            $entryTrendTolerance = $this->config->entryTrendTolerance ?: $this->config->entryEmaTolerance;
            $fastAboveMid = $entryFastValue >= $entryMidValue * (1 - $entryTrendTolerance);
            $midAboveSlow = $entryMidValue >= $entrySlowValue * (1 - $entryTrendTolerance);
            $fastBelowMid = $entryFastValue <= $entryMidValue * (1 + $entryTrendTolerance);
            $midBelowSlow = $entryMidValue <= $entrySlowValue * (1 + $entryTrendTolerance);
            $closeAboveSlow = $candle->close >= $entrySlowValue * (1 - $entryTrendTolerance);
            $closeBelowSlow = $candle->close <= $entrySlowValue * (1 + $entryTrendTolerance);

            $entryBull = $fastAboveMid && $midAboveSlow && $closeAboveSlow;
            $entryBear = $fastBelowMid && $midBelowSlow && $closeBelowSlow;

            if ($trend === 'up' && !$entryBull) {
                $debugStats['entryMismatch'] += 1;
                continue;
            }
            if ($trend === 'down' && !$entryBear) {
                $debugStats['entryMismatch'] += 1;
                continue;
            }
            if (!$entryTouchOk) {
                $debugStats['emaTouchMiss'] += 1;
                continue;
            }

            $direction = $trend === 'up' ? 'LONG' : 'SHORT';
            if ($direction === 'LONG' && !$this->config->allowLong) {
                $debugStats['directionBlocked'] += 1;
                continue;
            }
            if ($direction === 'SHORT' && !$this->config->allowShort) {
                $debugStats['directionBlocked'] += 1;
                continue;
            }

            $widgetMeta = [
                'contextFastSlowDiff' => $fastDiff,
                'entryFastSlope' => $this->calcSlope($entryEmaFast, $i, 10),
                'entryMidSlope' => $this->calcSlope($entryEmaMid, $i, 10),
                'entrySlowSlope' => $this->calcSlope($entryEmaSlow, $i, 10),
                'entryFastSlope3' => $this->calcSlope($entryEmaFast, $i, 3),
                'entryMidSlope3' => $this->calcSlope($entryEmaMid, $i, 3),
                'entrySlowSlope3' => $this->calcSlope($entryEmaSlow, $i, 3),
                'volumeRatio' => (is_finite($volumeSma[$i] ?? NAN) && ($volumeSma[$i] ?? 0.0) > 0)
                    ? ($candle->volume / $volumeSma[$i])
                    : 1.0,
                'volumeOverSma' => (is_finite($volumeSma[$i] ?? NAN) && ($volumeSma[$i] ?? 0.0) > 0)
                    ? ($candle->volume / $volumeSma[$i])
                    : 1.0,
                'rangeOverAtr' => $atrValue > 0 ? ($range / $atrValue) : 1.0,
                'atrPercent' => $candle->close > 0 ? (($atrValue / $candle->close) * 100.0) : 0.0,
                'bodyRatio' => $bodyRatio,
                'entrySlowDistPct' => $entrySlowValue > 0 ? (($candle->close - $entrySlowValue) / $entrySlowValue) : 0.0,
            ];

            $signal = $this->buildSignal(
                direction: $direction,
                candleIndex: $i,
                candle: $candle,
                candles: $filteredCandles,
                atrValue: $atrValue,
                area: $area,
                spikes: $spikes,
                symbol: $instrumentData->symbol,
                exchange: $instrumentData->exchange,
                priceStep: $instrumentData->priceStep,
                intervalMs: $intervalMs,
                debugStats: $debugStats,
            );

            if ($signal) {
                $widgetMeta['stopDistancePct'] = $signal->entryPrice > 0
                    ? (abs($signal->entryPrice - $signal->stopLoss) / $signal->entryPrice)
                    : 0.0;
                if (!$this->passesWidgetFilter($signal->direction, $widgetMeta)) {
                    $debugStats['widgetRejected'] += 1;
                    continue;
                }
                $signals[] = $signal;
                $debugStats['tradesOpened'] += 1;
            }
        }

        return [
            'signals' => $signals,
            'debug' => $debug ? $debugStats : null,
        ];
    }

    /**
     * @param array<string, int|float> $debugStats
     */
    private function buildEntrySignal(
        string $direction,
        int $candleIndex,
        Candle $candle,
        array $candles,
        float $atrValue,
        SpikeArea $area,
        array $spikes,
        string $symbol,
        string $exchange,
        float $priceStep,
        int $intervalMs,
        array &$debugStats,
    ): ?VolumeSignal {
        $entryMode = strtolower($this->config->entryMode ?: 'close');
        $entryTimeMs = $candle->openTimeMs + $intervalMs;
        $baseEntryPrice = $candle->close;

        if ($entryMode === 'next_open') {
            $next = $candles[$candleIndex + 1] ?? null;
            if (!$next) {
                $debugStats['entryNextCandleUnavailable'] += 1;
                return null;
            }

            $entryTimeMs = $next->openTimeMs;
            $baseEntryPrice = $next->open;
        }

        $entryPrice = $baseEntryPrice;
        $entryPrice = Slippage::applySpread($entryPrice, $direction, 'entry', $this->config->spreadBps);
        $entryPrice = Slippage::applyTradeSlippage($entryPrice, $direction, 'entry', $this->config->tradeSlippageBps);
        $entryPrice = PriceStep::applyEntryPriceStep($entryPrice, $direction, $priceStep);

        $swingLow = $this->getSwingLow($candles, $candleIndex, $this->config->swingLookback);
        $swingHigh = $this->getSwingHigh($candles, $candleIndex, $this->config->swingLookback);
        $stopReference = strtoupper($direction) === 'LONG'
            ? min($area->level, $candle->low, $swingLow)
            : max($area->level, $candle->high, $swingHigh);
        $rawStopLoss = strtoupper($direction) === 'LONG'
            ? $stopReference - $atrValue * $this->config->stopAtrMultiplier
            : $stopReference + $atrValue * $this->config->stopAtrMultiplier;
        $stopLoss = PriceStep::applyStopPriceStep($rawStopLoss, $direction, $priceStep);

        if (!is_finite($stopLoss) || $stopLoss <= 0) {
            return null;
        }

        if ((strtoupper($direction) === 'LONG' && $stopLoss >= $entryPrice) || (strtoupper($direction) === 'SHORT' && $stopLoss <= $entryPrice)) {
            return null;
        }

        $distance = abs($entryPrice - $stopLoss);
        if ($distance <= 0) {
            return null;
        }

        $stopPercent = $distance / $entryPrice;
        if ($stopPercent < max(0.0, $this->config->minStopPercent)) {
            $debugStats['stopTooTight'] += 1;
            return null;
        }

        if ($this->config->maxStopPercent > 0 && $stopPercent > $this->config->maxStopPercent) {
            $debugStats['stopTooWide'] += 1;
            return null;
        }

        $normalizedPriceStep = PriceStep::normalizePriceStep($priceStep);
        if ($normalizedPriceStep > 0) {
            $stopTicks = $distance / $normalizedPriceStep;
            if ($this->config->minStopTicks > 0 && $stopTicks < $this->config->minStopTicks) {
                $debugStats['stopTooTightTicks'] += 1;
                return null;
            }
            if ($this->config->maxStopTicks > 0 && $stopTicks > $this->config->maxStopTicks) {
                $debugStats['stopTooWideTicks'] += 1;
                return null;
            }
        }

        $takeProfitRaw = strtoupper($direction) === 'LONG'
            ? $entryPrice + ($distance * $this->config->rewardMultiplier)
            : $entryPrice - ($distance * $this->config->rewardMultiplier);

        $targetLevel = $this->findTargetLevel($spikes, $candleIndex, $direction, $entryPrice);
        if ($targetLevel !== null) {
            if (strtoupper($direction) === 'LONG') {
                $takeProfitRaw = min($takeProfitRaw, $targetLevel);
            } else {
                $takeProfitRaw = max($takeProfitRaw, $targetLevel);
            }
        }

        $takeProfit = PriceStep::applyTakePriceStep($takeProfitRaw, $direction, $priceStep);
        $rewardRatio = abs(($takeProfit - $entryPrice) / ($entryPrice - $stopLoss));
        if (!is_finite($rewardRatio) || $rewardRatio < $this->config->minRewardRatio) {
            return null;
        }

        return new VolumeSignal(
            symbol: $symbol,
            exchange: $exchange,
            direction: strtoupper($direction),
            entryTimeMs: $entryTimeMs,
            entryPrice: $entryPrice,
            stopLoss: $stopLoss,
            takeProfit: $takeProfit,
            rewardRisk: $rewardRatio,
            level: $area->level,
        );
    }

    /**
     * @param array<string, int|float> $debugStats
     */
    private function buildSignal(
        string $direction,
        int $candleIndex,
        Candle $candle,
        array $candles,
        float $atrValue,
        SpikeArea $area,
        array $spikes,
        string $symbol,
        string $exchange,
        float $priceStep,
        int $intervalMs,
        array &$debugStats,
    ): ?VolumeSignal {
        $entry = $this->buildEntrySignal(
            direction: $direction,
            candleIndex: $candleIndex,
            candle: $candle,
            candles: $candles,
            atrValue: $atrValue,
            area: $area,
            spikes: $spikes,
            symbol: $symbol,
            exchange: $exchange,
            priceStep: $priceStep,
            intervalMs: $intervalMs,
            debugStats: $debugStats,
        );

        if (!$entry) {
            return null;
        }

        $result = $this->simulateTrade(
            entryIndex: $candleIndex,
            direction: $direction,
            stopLoss: $entry->stopLoss,
            takeProfit: $entry->takeProfit,
            candles: $candles,
            priceStep: $priceStep,
            intervalMs: $intervalMs,
        );

        if (!$result) {
            return null;
        }

        return new VolumeSignal(
            symbol: $entry->symbol,
            exchange: $entry->exchange,
            direction: $entry->direction,
            entryTimeMs: $entry->entryTimeMs,
            entryPrice: $entry->entryPrice,
            stopLoss: $entry->stopLoss,
            takeProfit: $entry->takeProfit,
            rewardRisk: $entry->rewardRisk,
            level: $entry->level,
            exitTimeMs: $result['exitTimeMs'],
            exitPrice: $result['exitPrice'],
        );
    }

    /**
     * @param array<int, Candle> $candles
     * @return array{exitTimeMs: int, exitPrice: float}|null
     */
    private function simulateTrade(
        int $entryIndex,
        string $direction,
        float $stopLoss,
        float $takeProfit,
        array $candles,
        float $priceStep,
        int $intervalMs,
    ): ?array {
        $sameBarPolicy = strtolower($this->config->sameCandleExit ?: 'stop');

        for ($i = $entryIndex + 1; $i < count($candles); $i += 1) {
            $candle = $candles[$i];
            if (strtoupper($direction) === 'LONG') {
                $stopHit = $candle->low <= $stopLoss;
                $takeHit = $candle->close >= $takeProfit;
                if ($stopHit && $takeHit && $sameBarPolicy === 'take') {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
                if ($stopHit) {
                    $base = $candle->open <= $stopLoss ? $candle->open : $stopLoss;
                    $exitPrice = Slippage::applySpread($base, $direction, 'exit', $this->config->spreadBps);
                    $exitPrice = Slippage::applyTradeSlippage($exitPrice, $direction, 'exit', $this->config->tradeSlippageBps);
                    $stopSlippageBps = $this->resolveDynamicStopSlippageBps($candle, $stopLoss, $direction);
                    $exitPrice = Slippage::applyStopSlippage($exitPrice, $direction, $stopSlippageBps);
                    return [
                        'exitPrice' => PriceStep::applyStopPriceStep($exitPrice, $direction, $priceStep),
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
                if ($takeHit) {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
            } else {
                $stopHit = $candle->high >= $stopLoss;
                $takeHit = $candle->close <= $takeProfit;
                if ($stopHit && $takeHit && $sameBarPolicy === 'take') {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
                if ($stopHit) {
                    $base = $candle->open >= $stopLoss ? $candle->open : $stopLoss;
                    $exitPrice = Slippage::applySpread($base, $direction, 'exit', $this->config->spreadBps);
                    $exitPrice = Slippage::applyTradeSlippage($exitPrice, $direction, 'exit', $this->config->tradeSlippageBps);
                    $stopSlippageBps = $this->resolveDynamicStopSlippageBps($candle, $stopLoss, $direction);
                    $exitPrice = Slippage::applyStopSlippage($exitPrice, $direction, $stopSlippageBps);
                    return [
                        'exitPrice' => PriceStep::applyStopPriceStep($exitPrice, $direction, $priceStep),
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
                if ($takeHit) {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => $this->closeTimeFor($intervalMs, $candles, $i),
                    ];
                }
            }
        }

        return null;
    }

    private function resolveDynamicStopSlippageBps(Candle $candle, float $stopLoss, string $direction): float
    {
        $reference = max(1e-9, (float) $candle->close);
        $rangeBps = max(0.0, (($candle->high - $candle->low) / $reference) * 10_000);
        $gapBps = Slippage::adverseStopGapBps($candle->open, $stopLoss, $direction);

        return Slippage::dynamicStopSlippageBps(
            baseBps: $this->config->stopSlippageBps,
            rangeBps: $rangeBps,
            gapBps: $gapBps,
            enabled: $this->config->dynamicStopSlippageEnabled,
            rangeTriggerBps: $this->config->dynamicStopRangeTriggerBps,
            rangeCoeff: $this->config->dynamicStopRangeCoeff,
            gapCoeff: $this->config->dynamicStopGapCoeff,
            capBps: $this->config->dynamicStopSlippageCapBps,
        );
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function closeTimeFor(int $intervalMs, array $candles, int $index): int
    {
        $ms = $this->resolveBarMs($intervalMs, $candles, $index);
        $candle = $candles[$index] ?? null;
        if ($candle && $ms > 0) {
            return $candle->openTimeMs + $ms;
        }

        return $candle?->openTimeMs ?? 0;
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function resolveBarMs(int $intervalMs, array $candles, int $index): int
    {
        if ($intervalMs > 0) {
            return $intervalMs;
        }

        $current = $candles[$index] ?? null;
        $next = $candles[$index + 1] ?? null;
        if ($current && $next) {
            return max(0, $next->openTimeMs - $current->openTimeMs);
        }

        return 0;
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function resolveIntervalMs(string $interval, array $candles): int
    {
        if (count($candles) > 1) {
            $first = $candles[0];
            $second = $candles[1];
            $diff = $second->openTimeMs - $first->openTimeMs;
            if ($diff > 0) {
                return $diff;
            }
        }

        return match (strtolower($interval)) {
            '1m' => 60_000,
            '3m' => 180_000,
            '5m' => 300_000,
            '15m' => 900_000,
            '30m' => 1_800_000,
            '1h' => 3_600_000,
            '2h' => 7_200_000,
            '4h' => 14_400_000,
            '6h' => 21_600_000,
            '12h' => 43_200_000,
            '1d' => 86_400_000,
            '1w' => 604_800_000,
            default => 0,
        };
    }

    /**
     * @param array<int, SpikeArea> $spikes
     * @param array<int, Candle> $candles
     */
    private function findArea(array $spikes, int $index, array $candles): ?SpikeArea
    {
        for ($i = count($spikes) - 1; $i >= 0; $i -= 1) {
            $area = $spikes[$i];
            if ($area->index >= $index) {
                continue;
            }
            if ($index - $area->index > $this->config->lookback) {
                break;
            }

            $candle = $candles[$index] ?? null;
            if (!$candle) {
                continue;
            }

            $price = $candle->hl2();
            $distance = abs(($price - $area->level) / $area->level);
            $touchesLevel = $candle->low <= $area->level && $area->level <= $candle->high;
            if ($distance <= $this->config->levelTolerance || $touchesLevel) {
                return $area;
            }
        }

        return null;
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function getSwingLow(array $candles, int $index, int $lookback = 10): float
    {
        $start = max(0, $index - $lookback);
        $min = $candles[$index]->low;
        for ($i = $start; $i <= $index; $i += 1) {
            $min = min($min, $candles[$i]->low);
        }
        return $min;
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function getSwingHigh(array $candles, int $index, int $lookback = 10): float
    {
        $start = max(0, $index - $lookback);
        $max = $candles[$index]->high;
        for ($i = $start; $i <= $index; $i += 1) {
            $max = max($max, $candles[$i]->high);
        }
        return $max;
    }

    /**
     * @param array<int, Candle> $contextCandles
     * @return array<int, float>
     */
    private function calculateContextVolumeSma(array $contextCandles): array
    {
        $period = max(1, $this->config->contextVolumePeriod);
        $volumes = array_map(fn (Candle $c) => $c->volume, $contextCandles);
        return Sma::calculate($volumes, $period);
    }

    /**
     * @param array<int, Candle> $contextCandles
     * @param array<int, float> $contextVolumeSma
     */
    private function checkContextVolumeImpulse(
        array $contextCandles,
        int $contextIndex,
        array $contextVolumeSma,
        float $contextVolumeMultiplier,
    ): bool {
        $multiplier = is_finite($contextVolumeMultiplier)
            ? $contextVolumeMultiplier
            : $this->config->contextVolumeMultiplier;

        if ($multiplier <= 0) {
            return true;
        }

        $current = $contextCandles[$contextIndex] ?? null;
        if (!$current) {
            return false;
        }

        $smaValue = $contextVolumeSma[$contextIndex] ?? NAN;
        if (!is_finite($smaValue) || $smaValue <= 0) {
            return false;
        }

        return $current->volume >= $smaValue * $multiplier;
    }

    /**
     * @param array<int, SpikeArea> $spikes
     */
    private function findTargetLevel(array $spikes, int $index, string $direction, float $entryPrice): ?float
    {
        $target = null;
        foreach ($spikes as $area) {
            if ($area->index > $index || $index - $area->index > $this->config->lookback) {
                continue;
            }

            if (strtoupper($direction) === 'LONG' && $area->direction === 'bearish' && $area->level > $entryPrice) {
                $target = $target === null ? $area->level : min($target, $area->level);
            }
            if (strtoupper($direction) === 'SHORT' && $area->direction === 'bullish' && $area->level < $entryPrice) {
                $target = $target === null ? $area->level : max($target, $area->level);
            }
        }

        return $target;
    }

    /** @param array<int, Candle> $candles */
    private function resolveContextIntervalMs(array $candles): int
    {
        $interval = 0;
        for ($i = 1; $i < count($candles); $i++) {
            $delta = $candles[$i]->openTimeMs - $candles[$i - 1]->openTimeMs;
            if ($delta > 0 && ($interval === 0 || $delta < $interval)) {
                $interval = $delta;
            }
        }

        return $interval;
    }

    /** @param array<int, Candle> $candles */
    private function advanceClosedContextIndex(array $candles, int $index, int $decisionTimeMs, int $intervalMs): int
    {
        if ($intervalMs <= 0) {
            return -1;
        }

        while (isset($candles[$index + 1]) && $candles[$index + 1]->openTimeMs + $intervalMs <= $decisionTimeMs) {
            $index++;
        }

        return $index;
    }

    /**
     * @param array<int, float> $rsi
     * @param array<int, array{adx: float, plusDI: float, minusDI: float}> $adx
     * @param array<int, float> $contextRsi
     * @param array<int, array{adx: float, plusDI: float, minusDI: float}> $contextAdx
     * @param array<int, float> $volumeSmaFast
     * @param array<int, float> $volumeSmaSlow
     * @param array<string, int|float> $debugStats
     */
    private function passesSmartFilters(
        string $trend,
        int $index,
        int $contextIndex,
        array $rsi,
        array $adx,
        array $contextRsi,
        array $contextAdx,
        array $volumeSmaFast,
        array $volumeSmaSlow,
        array &$debugStats,
    ): bool {
        $rsiValue = $rsi[$index] ?? NAN;
        if (!is_finite($rsiValue)) {
            $debugStats['rsiUnavailable'] += 1;
            return false;
        }
        if ($trend === 'up' && $rsiValue < $this->config->rsiLongMin) {
            $debugStats['rsiRejected'] += 1;
            return false;
        }
        if ($trend === 'down' && $rsiValue > $this->config->rsiShortMax) {
            $debugStats['rsiRejected'] += 1;
            return false;
        }

        $contextRsiValue = $contextRsi[$contextIndex] ?? NAN;
        if (!is_finite($contextRsiValue)) {
            $debugStats['contextRsiUnavailable'] += 1;
            return false;
        }
        if ($trend === 'up' && $contextRsiValue < $this->config->contextRsiLongMin) {
            $debugStats['contextRsiRejected'] += 1;
            return false;
        }
        if ($trend === 'down' && $contextRsiValue > $this->config->contextRsiShortMax) {
            $debugStats['contextRsiRejected'] += 1;
            return false;
        }

        $contextAdxRow = $contextAdx[$contextIndex] ?? ['adx' => NAN, 'plusDI' => NAN, 'minusDI' => NAN];
        if ($this->config->contextMinAdx > 0) {
            if (!is_finite($contextAdxRow['adx'])) {
                $debugStats['contextAdxUnavailable'] += 1;
                return false;
            }
            if ($contextAdxRow['adx'] < $this->config->contextMinAdx) {
                $debugStats['contextAdxRejected'] += 1;
                return false;
            }
        }
        if ($this->config->contextRequireDiAlignment) {
            if (!is_finite($contextAdxRow['plusDI']) || !is_finite($contextAdxRow['minusDI'])) {
                $debugStats['contextDiUnavailable'] += 1;
                return false;
            }
            if ($trend === 'up' && $contextAdxRow['plusDI'] < $contextAdxRow['minusDI']) {
                $debugStats['contextDiRejected'] += 1;
                return false;
            }
            if ($trend === 'down' && $contextAdxRow['minusDI'] < $contextAdxRow['plusDI']) {
                $debugStats['contextDiRejected'] += 1;
                return false;
            }
        }

        $adxRow = $adx[$index] ?? ['adx' => NAN, 'plusDI' => NAN, 'minusDI' => NAN];
        if ($this->config->minAdx > 0) {
            if (!is_finite($adxRow['adx'])) {
                $debugStats['adxUnavailable'] += 1;
                return false;
            }
            if ($adxRow['adx'] < $this->config->minAdx) {
                $debugStats['adxRejected'] += 1;
                return false;
            }
        }
        if ($this->config->requireDiAlignment) {
            if (!is_finite($adxRow['plusDI']) || !is_finite($adxRow['minusDI'])) {
                $debugStats['diUnavailable'] += 1;
                return false;
            }
            if ($trend === 'up' && $adxRow['plusDI'] < $adxRow['minusDI']) {
                $debugStats['diRejected'] += 1;
                return false;
            }
            if ($trend === 'down' && $adxRow['minusDI'] < $adxRow['plusDI']) {
                $debugStats['diRejected'] += 1;
                return false;
            }
        }

        if ($this->config->minVolumeTrendRatio > 0) {
            $fastVol = $volumeSmaFast[$index] ?? NAN;
            $slowVol = $volumeSmaSlow[$index] ?? NAN;
            if (!is_finite($fastVol) || !is_finite($slowVol) || $slowVol <= 0) {
                $debugStats['volumeTrendUnavailable'] += 1;
                return false;
            }

            $volRatio = $fastVol / $slowVol;
            if (!is_finite($volRatio) || $volRatio < $this->config->minVolumeTrendRatio) {
                $debugStats['volumeTrendRejected'] += 1;
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int, float> $series
     */
    private function calcSlope(array $series, int $index, int $lookback = 10): ?float
    {
        $startIndex = $index - $lookback;
        if ($startIndex < 0) {
            return null;
        }

        $start = $series[$startIndex] ?? NAN;
        $end = $series[$index] ?? NAN;
        if (!is_finite($start) || !is_finite($end) || $start == 0.0) {
            return null;
        }

        return ($end - $start) / $start;
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function passesWidgetFilter(string $direction, array $meta): bool
    {
        if (!$this->config->widgetFilter) {
            return true;
        }

        $model = $this->buildWidgetModel($direction, $meta);
        $allowRegimes = $this->parseAllowedRegimes($this->config->widgetAllowRegimes);
        if (!in_array($model['regimeState'], $allowRegimes, true)) {
            return false;
        }

        $minTrendScore = self::clamp($this->num($this->config->widgetMinTrendScore, 30.0), 0.0, 100.0);
        $minMomentumScore = self::clamp($this->num($this->config->widgetMinMomentumScore, 30.0), 0.0, 100.0);
        $maxRiskScore = self::clamp($this->num($this->config->widgetMaxRiskScore, 60.0), 0.0, 100.0);
        $minRegimeConfidence = self::clamp($this->num($this->config->widgetMinRegimeConfidence, 25.0), 0.0, 100.0);
        $maxShiftRisk = self::clamp($this->num($this->config->widgetMaxShiftRisk, 65.0), 0.0, 100.0);
        $minTrendHealth = self::clamp($this->num($this->config->widgetMinTrendHealth, 0.0), 0.0, 100.0);
        $maxMeanReversionEdge = self::clamp($this->num($this->config->widgetMaxMeanReversionEdge, 100.0), 0.0, 100.0);

        if ($model['regimeConfidence'] < $minRegimeConfidence) {
            return false;
        }
        if ($model['shiftRisk'] > $maxShiftRisk) {
            return false;
        }
        if ($model['trendScore'] < $minTrendScore) {
            return false;
        }
        if ($model['momentumScore'] < $minMomentumScore) {
            return false;
        }
        if ($model['riskScore'] > $maxRiskScore) {
            return false;
        }
        if ($model['trendHealth'] < $minTrendHealth) {
            return false;
        }
        if ($model['meanReversionEdge'] > $maxMeanReversionEdge) {
            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $meta
     * @return array{
     *   trendScore: float,
     *   momentumScore: float,
     *   riskScore: float,
     *   regimeState: string,
     *   regimeConfidence: float,
     *   shiftRisk: float,
     *   trendHealth: float,
     *   meanReversionEdge: float
     * }
     */
    private function buildWidgetModel(string $direction, array $meta): array
    {
        $contextDiffAbs = abs($this->num($meta['contextFastSlowDiff'] ?? 0.0));
        $trendStrength = self::clamp(($contextDiffAbs / 0.01) * 100.0, 0.0, 100.0);

        $slope10 = [
            $this->signedAlignment($direction, $meta['entryFastSlope10'] ?? ($meta['entryFastSlope'] ?? 0.0)),
            $this->signedAlignment($direction, $meta['entryMidSlope10'] ?? ($meta['entryMidSlope'] ?? 0.0)),
            $this->signedAlignment($direction, $meta['entrySlowSlope10'] ?? ($meta['entrySlowSlope'] ?? 0.0)),
        ];
        $slope3 = [
            $this->signedAlignment($direction, $meta['entryFastSlope3'] ?? 0.0),
            $this->signedAlignment($direction, $meta['entryMidSlope3'] ?? 0.0),
            $this->signedAlignment($direction, $meta['entrySlowSlope3'] ?? 0.0),
        ];

        $slopeAlignedShare = count(array_filter($slope10, static fn (float $v): bool => $v > 0)) / 3.0;
        $slopeConflict = count(array_filter($slope10, static fn (float $v): bool => $v < 0)) >= 2;
        $accelPositiveShare = count(array_filter($slope3, static fn (float $v): bool => $v > 0)) / 3.0;

        $trendScore = self::clamp(($trendStrength * 0.58) + ($slopeAlignedShare * 100.0 * 0.42), 0.0, 100.0);

        $volumeOverSma = $this->num($meta['volumeOverSma'] ?? ($meta['volumeRatio'] ?? 1.0), 1.0);
        $volumeScore = self::clamp(45.0 + (($volumeOverSma - 1.0) * 60.0), 0.0, 100.0);
        $rangeOverAtr = $this->num($meta['rangeOverAtr'] ?? 1.0, 1.0);
        $impulseScore = self::clamp(($rangeOverAtr - 0.55) * 70.0, 0.0, 100.0);
        $accelerationScore = self::clamp($accelPositiveShare * 100.0, 0.0, 100.0);
        $momentumScore = self::clamp(
            ($volumeScore * 0.42) + ($impulseScore * 0.38) + ($accelerationScore * 0.20),
            0.0,
            100.0
        );

        $stopRisk = self::clamp(($this->num($meta['stopDistancePct'] ?? 0.0, 0.0) / 0.02) * 100.0, 0.0, 100.0);
        $atrRisk = self::clamp(($this->num($meta['atrPercent'] ?? 0.0, 0.0) / 2.2) * 100.0, 0.0, 100.0);
        $bodyRatio = $this->num($meta['bodyRatio'] ?? 0.5, 0.5);
        $structureRisk = self::clamp((0.66 - $bodyRatio) * 130.0, 0.0, 100.0);
        $volatilityShockRisk = self::clamp(($volumeOverSma - 1.7) * 120.0, 0.0, 100.0);
        $riskScore = self::clamp(
            ($stopRisk * 0.40) + ($atrRisk * 0.25) + ($structureRisk * 0.25) + ($volatilityShockRisk * 0.10),
            0.0,
            100.0
        );

        $regimeState = 'Range';
        if ($trendScore >= 65.0 && $momentumScore >= 45.0) {
            $regimeState = 'Trend';
        } elseif ($momentumScore >= 72.0 && $riskScore >= 60.0) {
            $regimeState = 'Expansion';
        } elseif ($momentumScore <= 35.0 && $riskScore <= 45.0) {
            $regimeState = 'Compression';
        }

        $regimeConfidenceRaw = match ($regimeState) {
            'Trend' => ($trendScore * 0.60) + ($momentumScore * 0.40),
            'Expansion' => ($momentumScore * 0.55) + ((100.0 - $riskScore) * 0.15) + ($trendScore * 0.30),
            'Compression' => ((100.0 - $momentumScore) * 0.55) + ((100.0 - $riskScore) * 0.30) + ((100.0 - $trendScore) * 0.15),
            default => ($trendScore * 0.35) + ($momentumScore * 0.30) + ((100.0 - $riskScore) * 0.35),
        };
        $regimeConfidence = self::clamp($regimeConfidenceRaw, 5.0, 95.0);

        $shiftRisk = self::clamp(
            (100.0 - $regimeConfidence)
            + ($riskScore > 55.0 ? (($riskScore - 55.0) * 0.7) : 0.0)
            + ($slopeConflict ? 14.0 : 0.0),
            5.0,
            95.0
        );

        $trendHealth = self::clamp(($trendScore * 0.70) + ($slopeAlignedShare * 100.0 * 0.30), 0.0, 100.0);

        $overextension = self::clamp(abs($this->num($meta['entrySlowDistPct'] ?? 0.0, 0.0)) * 12000.0, 0.0, 100.0);
        $meanReversionEdge = self::clamp(
            ($overextension * 0.45) + ((100.0 - $trendHealth) * 0.25) + ($slopeConflict ? 35.0 : 0.0) * 0.30,
            0.0,
            100.0
        );

        return [
            'trendScore' => $trendScore,
            'momentumScore' => $momentumScore,
            'riskScore' => $riskScore,
            'regimeState' => $regimeState,
            'regimeConfidence' => $regimeConfidence,
            'shiftRisk' => $shiftRisk,
            'trendHealth' => $trendHealth,
            'meanReversionEdge' => $meanReversionEdge,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function parseAllowedRegimes(string $raw): array
    {
        $parts = array_filter(array_map(
            fn (string $p): ?string => $this->canonicalRegime($p),
            array_map('trim', explode(',', $raw))
        ));
        $unique = array_values(array_unique($parts));

        return $unique !== [] ? $unique : self::ALL_REGIMES;
    }

    private function canonicalRegime(string $value): ?string
    {
        return match (strtolower(trim($value))) {
            'trend' => 'Trend',
            'expansion' => 'Expansion',
            'range' => 'Range',
            'compression' => 'Compression',
            default => null,
        };
    }

    private function signedAlignment(string $direction, mixed $value): float
    {
        $sign = strtoupper($direction) === 'LONG' ? 1.0 : -1.0;

        return $sign * $this->num($value, 0.0);
    }

    private function num(mixed $value, float $fallback = 0.0): float
    {
        if (is_numeric($value)) {
            $parsed = (float) $value;
            if (is_finite($parsed)) {
                return $parsed;
            }
        }

        return $fallback;
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /**
     * @param array<string, int|float> $seed
     * @return array<string, int|float>
     */
    private function createEmptyDebug(array $seed = []): array
    {
        return array_merge([
            'totalCandles' => 0,
            'spikes' => 0,
            'zeroVolumeFiltered' => 0,
            'contextZeroVolumeFiltered' => 0,
            'atrUnavailable' => 0,
            'contextUnavailable' => 0,
            'entryUnavailable' => 0,
            'noArea' => 0,
            'levelMismatch' => 0,
            'stopTooTight' => 0,
            'stopTooWide' => 0,
            'stopTooTightTicks' => 0,
            'stopTooWideTicks' => 0,
            'volumeRejected' => 0,
            'trendMismatch' => 0,
            'trendWeak' => 0,
            'entryMismatch' => 0,
            'emaTouchMiss' => 0,
            'rsiUnavailable' => 0,
            'rsiRejected' => 0,
            'contextRsiUnavailable' => 0,
            'contextRsiRejected' => 0,
            'contextAdxUnavailable' => 0,
            'contextAdxRejected' => 0,
            'contextDiUnavailable' => 0,
            'contextDiRejected' => 0,
            'adxUnavailable' => 0,
            'adxRejected' => 0,
            'diUnavailable' => 0,
            'diRejected' => 0,
            'volumeTrendUnavailable' => 0,
            'volumeTrendRejected' => 0,
            'candleRejected' => 0,
            'areaDirectionMismatch' => 0,
            'directionBlocked' => 0,
            'widgetRejected' => 0,
            'tradesOpened' => 0,
            'contextVolumeRejected' => 0,
            'entryNextCandleUnavailable' => 0,
        ], $seed);
    }

    private function confirmVolume(Candle $candle, SpikeArea $area, float $volumeSmaValue): bool
    {
        if (!is_finite($volumeSmaValue) || $volumeSmaValue <= 0) {
            return false;
        }

        $threshold = $volumeSmaValue * $this->config->confirmVolumeMultiplier;
        $baselineOk = $candle->volume >= $threshold;
        $areaOk = $candle->volume >= ($area->volume * $this->config->areaConfirmFactor);

        return $baselineOk || $areaOk;
    }
}
