<?php

namespace App\Services\Analytics;

use App\DTOs\Trading\Candle;
use App\Services\MarketData\MarketDataService;
use App\Services\Trading\Indicators\Adx;
use App\Services\Trading\Indicators\Atr;
use App\Services\Trading\Indicators\Ema;
use App\Services\Trading\Indicators\Rsi;
use App\Services\Trading\Indicators\Sma;
use Illuminate\Support\Facades\Cache;

final class BlacklineAnalyticsService
{
    public function __construct(
        private readonly MarketDataService $marketData,
    ) {}

    /**
     * @return array{
     *   blackline: array<string, float|int|string>,
     *   regime: array<string, float|int|string|array<int, array{name: string, probability: int}>>,
     *   customIndicators: array<int, array{key: string, score: int, status: string}>,
     * }
     */
    public function analyze(string $symbol, string $interval, int $limit = 320): array
    {
        $symbol = strtoupper($symbol);
        $interval = strtolower($interval);
        $provider = (string) config('terminal.exchange', 'bybit');

        $cacheKey = sprintf('blackline_analytics:%s:%s:%d:%s', $symbol, $interval, $limit, $provider);
        $cacheTtlSeconds = $this->analyticsCacheTtlSeconds($interval);

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($symbol, $interval, $limit, $provider): array {
            $candles = $this->marketData->fetchKlines(
                symbol: $symbol,
                interval: $interval,
                limit: max(120, $limit),
                provider: $provider,
            );

            if (count($candles) < 80) {
                return $this->emptyPayload();
            }

            return [
                'blackline' => $this->buildBlacklineScore($candles),
                'regime' => $this->buildRegimeEngine($candles),
                'customIndicators' => $this->buildCustomIndicators($candles),
            ];
        });
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<string, float|int|string>
     */
    private function buildBlacklineScore(array $candles): array
    {
        $close = array_map(static fn (Candle $c): float => $c->close, $candles);
        $volume = array_map(static fn (Candle $c): float => $c->volume, $candles);

        $ema21 = Ema::calculate($close, 21);
        $ema50 = Ema::calculate($close, 50);
        $ema200 = Ema::calculate($close, 200);
        $rsi14 = Rsi::calculate($close, 14);
        $atr14 = Atr::calculate($candles, 14);
        $adx14 = Adx::calculate($candles, 14);
        $volSma20 = Sma::calculate($volume, 20);

        $lastClose = (float) end($close);
        $lastVolume = (float) end($volume);
        $lastEma21 = $this->latestFinite($ema21);
        $lastEma50 = $this->latestFinite($ema50);
        $lastEma200 = $this->latestFinite($ema200);
        $lastRsi = $this->latestFinite($rsi14, 50.0);
        $lastAtr = $this->latestFinite($atr14);
        $lastVolSma = $this->latestFinite($volSma20);
        $lastAdxRow = $this->latestAdx($adx14);

        if ($lastEma21 === null || $lastEma50 === null || $lastEma200 === null || $lastAtr === null || $lastVolSma === null || $lastAdxRow === null || $lastClose <= 0.0) {
            return [
                'score' => 0,
                'trend' => 0,
                'momentum' => 0,
                'volatility' => 0,
                'volume' => 0,
                'risk' => 0,
                'bias' => 'NEUTRAL',
            ];
        }

        $trendAlignedBull = $lastClose > $lastEma21 && $lastEma21 > $lastEma50 && $lastEma50 > $lastEma200;
        $trendAlignedBear = $lastClose < $lastEma21 && $lastEma21 < $lastEma50 && $lastEma50 < $lastEma200;
        $bias = $trendAlignedBull ? 'LONG' : ($trendAlignedBear ? 'SHORT' : 'NEUTRAL');

        $adx = (float) $lastAdxRow['adx'];
        $trendBase = $trendAlignedBull || $trendAlignedBear ? 68.0 : 34.0;
        $trendStrength = $this->clamp((($adx - 12.0) / 30.0) * 32.0, 0.0, 32.0);
        $trendScore = (int) round($this->clamp($trendBase + $trendStrength, 0.0, 100.0));

        $atrPct = ($lastAtr / $lastClose) * 100.0;
        $roc10 = $this->percentChange($close, 10);
        $roc3 = $this->percentChange($close, 3);
        $rsiStrength = $this->clamp((abs($lastRsi - 50.0) / 25.0) * 55.0, 0.0, 55.0);
        $rocStrength = $this->clamp((abs($roc10) / max(0.01, $atrPct)) * 8.0, 0.0, 45.0);
        $reversalPenalty = ($roc10 * $roc3 < 0) ? 12.0 : 0.0;
        $momentumScore = (int) round($this->clamp($rsiStrength + $rocStrength - $reversalPenalty, 0.0, 100.0));

        $volatilityScore = (int) round($this->bandScore(
            value: $atrPct,
            hardLow: 0.15,
            idealLow: 0.55,
            idealHigh: 2.9,
            hardHigh: 6.5,
        ));

        $volumeRatio = $lastVolSma > 0 ? ($lastVolume / $lastVolSma) : 1.0;
        $volumeScore = (int) round($this->clamp((($volumeRatio - 0.7) / 1.2) * 100.0, 0.0, 100.0));

        $avgBodyRatio = $this->averageBodyRatio(array_slice($candles, -24));
        $riskFromStructure = $this->clamp($avgBodyRatio * 100.0, 0.0, 100.0);
        $riskFromVolatility = $this->bandScore(
            value: $atrPct,
            hardLow: 0.1,
            idealLow: 0.45,
            idealHigh: 2.4,
            hardHigh: 7.5,
        );
        $riskScore = (int) round(($riskFromStructure * 0.55) + ($riskFromVolatility * 0.45));

        $score = (int) round(
            ($trendScore * 0.30)
            + ($momentumScore * 0.25)
            + ($volatilityScore * 0.15)
            + ($volumeScore * 0.15)
            + ($riskScore * 0.15)
        );

        return [
            'score' => $score,
            'trend' => $trendScore,
            'momentum' => $momentumScore,
            'volatility' => $volatilityScore,
            'volume' => $volumeScore,
            'risk' => $riskScore,
            'bias' => $bias,
        ];
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<string, float|int|string|array<int, array{name: string, probability: int}>>
     */
    private function buildRegimeEngine(array $candles): array
    {
        $close = array_map(static fn (Candle $c): float => $c->close, $candles);
        $ema50 = Ema::calculate($close, 50);
        $rsi14 = Rsi::calculate($close, 14);
        $atr14 = Atr::calculate($candles, 14);
        $adx14 = Adx::calculate($candles, 14);

        $lastClose = (float) end($close);
        $lastEma50 = $this->latestFinite($ema50);
        $lastRsi = $this->latestFinite($rsi14, 50.0);
        $lastAtr = $this->latestFinite($atr14);
        $lastAdxRow = $this->latestAdx($adx14);

        if ($lastEma50 === null || $lastAtr === null || $lastAdxRow === null || $lastClose <= 0.0) {
            return [
                'state' => 'Range',
                'confidence' => 0,
                'shiftProbability' => 0,
                'nextStates' => [
                    ['name' => 'Trend', 'probability' => 0],
                    ['name' => 'Range', 'probability' => 0],
                    ['name' => 'Compression', 'probability' => 0],
                    ['name' => 'Expansion', 'probability' => 0],
                ],
            ];
        }

        $adx = (float) $lastAdxRow['adx'];
        $atrPct = ($lastAtr / $lastClose) * 100.0;

        $bbWidthNow = $this->bollingerWidth($close, 20, 2.0);
        $bbWidthPrev = $this->bollingerWidth(array_slice($close, 0, -1), 20, 2.0);
        $bbWidthHistory = $this->bollingerWidthSeries($close, 20, 2.0);
        $bbMedian = $this->median(array_slice($bbWidthHistory, -60));

        $emaDistancePct = abs(($lastClose - $lastEma50) / $lastEma50) * 100.0;
        $trendSignal = $adx >= 22.0 && $emaDistancePct >= max(0.35, $atrPct * 0.45);

        $compressionSignal = $bbMedian > 0 && $bbWidthNow <= ($bbMedian * 0.70) && $atrPct < 1.0;
        $expansionSignal = $bbMedian > 0 && $bbWidthNow >= ($bbMedian * 1.35) && $bbWidthNow > $bbWidthPrev;

        if ($trendSignal && !$compressionSignal && !$expansionSignal) {
            $state = 'Trend';
        } elseif ($compressionSignal) {
            $state = 'Compression';
        } elseif ($expansionSignal) {
            $state = 'Expansion';
        } else {
            $state = 'Range';
        }

        $stateConfidence = match ($state) {
            'Trend' => $this->clamp(($adx - 18.0) * 4.2, 40.0, 95.0),
            'Compression' => $this->clamp(95.0 - ($bbWidthNow * 8.0), 35.0, 92.0),
            'Expansion' => $this->clamp(45.0 + (($bbWidthNow - $bbMedian) * 16.0), 35.0, 90.0),
            default => $this->clamp(78.0 - abs($lastRsi - 50.0), 35.0, 85.0),
        };

        $adxSlope = $this->latestAdxSlope($adx14, 6);
        $momentumFlip = $this->percentChange($close, 3) * $this->percentChange($close, 10) < 0;
        $volShock = $bbMedian > 0 ? ($bbWidthNow / $bbMedian) : 1.0;

        $shiftProbability = (int) round($this->clamp(
            22.0
            + max(0.0, -$adxSlope) * 18.0
            + ($momentumFlip ? 18.0 : 0.0)
            + max(0.0, ($volShock - 1.4) * 35.0),
            5.0,
            92.0,
        ));

        $nextStates = $this->nextStateProbabilities($state, $shiftProbability);

        return [
            'state' => $state,
            'confidence' => (int) round($stateConfidence),
            'shiftProbability' => $shiftProbability,
            'nextStates' => $nextStates,
        ];
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<int, array{key: string, score: int, status: string}>
     */
    private function buildCustomIndicators(array $candles): array
    {
        $close = array_map(static fn (Candle $c): float => $c->close, $candles);
        $volume = array_map(static fn (Candle $c): float => $c->volume, $candles);

        $ema21 = Ema::calculate($close, 21);
        $ema50 = Ema::calculate($close, 50);
        $ema200 = Ema::calculate($close, 200);
        $rsi14 = Rsi::calculate($close, 14);
        $atr14 = Atr::calculate($candles, 14);
        $adx14 = Adx::calculate($candles, 14);
        $volSma20 = Sma::calculate($volume, 20);

        $lastClose = (float) end($close);
        $lastEma21 = $this->latestFinite($ema21);
        $lastEma50 = $this->latestFinite($ema50);
        $lastEma200 = $this->latestFinite($ema200);
        $lastRsi = $this->latestFinite($rsi14, 50.0);
        $lastAtr = $this->latestFinite($atr14, 0.0);
        $lastAdx = $this->latestAdx($adx14);
        $lastVol = (float) end($volume);
        $lastVolSma = $this->latestFinite($volSma20, max(1.0, $lastVol));

        $atrPct = $lastClose > 0 ? ($lastAtr / $lastClose) * 100.0 : 0.0;
        $roc3 = $this->percentChange($close, 3);
        $roc10 = $this->percentChange($close, 10);
        $roc21 = $this->percentChange($close, 21);
        $volRatio = $lastVolSma > 0 ? ($lastVol / $lastVolSma) : 1.0;

        $adxValue = $lastAdx ? (float) $lastAdx['adx'] : 0.0;
        $adxSlope = $this->latestAdxSlope($adx14, 8);
        $trendConsistency = $this->trendConsistency($close, 20);
        $wickRatio = $this->averageWickRatio(array_slice($candles, -24));
        $bodyRatio = $this->averageBodyRatio(array_slice($candles, -24));

        $emaAligned = $lastEma21 !== null && $lastEma50 !== null && $lastEma200 !== null
            ? (($lastEma21 > $lastEma50 && $lastEma50 > $lastEma200) || ($lastEma21 < $lastEma50 && $lastEma50 < $lastEma200))
            : false;

        $emaSpread = ($lastEma21 !== null && $lastEma50 !== null && $lastClose > 0)
            ? abs(($lastEma21 - $lastEma50) / $lastClose) * 100.0
            : 0.0;

        $trendHealthScore = (int) round($this->clamp(
            ($emaAligned ? 45.0 : 20.0)
            + $this->clamp(($adxValue - 12.0) * 2.1, 0.0, 35.0)
            + $this->clamp($trendConsistency * 20.0, 0.0, 20.0)
            + $this->clamp($emaSpread * 8.0, 0.0, 12.0),
            0.0,
            100.0,
        ));

        $momentumQualityScore = (int) round($this->clamp(
            $this->clamp((abs($lastRsi - 50.0) / 25.0) * 45.0, 0.0, 45.0)
            + $this->clamp((abs($roc10) / max(0.05, $atrPct)) * 4.0, 0.0, 35.0)
            + ($roc3 * $roc10 > 0 ? 20.0 : 0.0),
            0.0,
            100.0,
        ));

        $bbNow = $this->bollingerWidth($close, 20, 2.0);
        $bbHistory = $this->bollingerWidthSeries($close, 20, 2.0);
        $bbMedian = $this->median(array_slice($bbHistory, -60));
        $volatilityRegimeScore = (int) round($this->clamp(
            ($this->bandScore($atrPct, 0.12, 0.45, 2.8, 6.8) * 0.70)
            + ($bbMedian > 0 ? $this->bandScore($bbNow / $bbMedian, 0.35, 0.75, 1.45, 2.4) * 0.30 : 0.0),
            0.0,
            100.0,
        ));

        $liquidityPressureScore = (int) round($this->clamp(
            $this->clamp((($volRatio - 0.75) / 1.1) * 60.0, 0.0, 60.0)
            + $this->clamp($bodyRatio * 35.0, 0.0, 25.0)
            + $this->clamp((1.0 - $wickRatio) * 20.0, 0.0, 15.0),
            0.0,
            100.0,
        ));

        $compression = $bbMedian > 0 ? $this->clamp((1.0 - ($bbNow / $bbMedian)) * 100.0, 0.0, 100.0) : 0.0;
        $breakoutReadinessScore = (int) round($this->clamp(
            ($compression * 0.45)
            + ($this->clamp($volRatio * 30.0, 0.0, 30.0) * 0.35)
            + ($adxSlope > 0 ? $this->clamp($adxSlope * 10.0, 0.0, 15.0) : 0.0)
            + ($roc3 * $roc10 > 0 ? 10.0 : 0.0),
            0.0,
            100.0,
        ));

        $reversalRiskRaw = $this->clamp(
            ($roc3 * $roc10 < 0 ? 28.0 : 0.0)
            + (abs($lastRsi - 50.0) > 24.0 ? 22.0 : 0.0)
            + ($volRatio > 1.9 ? 18.0 : 0.0)
            + ($adxValue < 17.0 ? 18.0 : 0.0)
            + ($wickRatio > 0.58 ? 14.0 : 0.0),
            0.0,
            100.0,
        );
        $reversalRiskScore = (int) round(100.0 - $reversalRiskRaw);

        $marketEfficiencyScore = (int) round($this->clamp(
            ($this->directionalEfficiency($close, 20) * 70.0)
            + ($trendConsistency * 30.0),
            0.0,
            100.0,
        ));

        $volatilityPressureScore = (int) round($this->clamp(
            $this->clamp(($atrPct / max(0.15, $bbMedian * 0.4)) * 45.0, 0.0, 65.0)
            + ($volRatio > 1.15 ? 20.0 : 8.0)
            + ($adxSlope > 0 ? 15.0 : 0.0),
            0.0,
            100.0,
        ));

        $trendExhaustionRaw = $this->clamp(
            (abs($lastRsi - 50.0) > 26.0 ? 30.0 : 0.0)
            + (abs($roc21) > max(2.0, $atrPct * 2.8) ? 28.0 : 0.0)
            + ($adxSlope < -0.8 ? 22.0 : 0.0)
            + ($wickRatio > 0.62 ? 18.0 : 0.0),
            0.0,
            100.0,
        );
        $trendExhaustionScore = (int) round(100.0 - $trendExhaustionRaw);

        $meanReversionEdgeScore = (int) round($this->clamp(
            (abs($lastRsi - 50.0) > 20.0 ? 25.0 : 8.0)
            + ($bbMedian > 0 && $bbNow > $bbMedian * 1.35 ? 25.0 : 10.0)
            + ($roc3 * $roc10 < 0 ? 28.0 : 0.0)
            + ($adxValue < 20.0 ? 18.0 : 6.0),
            0.0,
            100.0,
        ));

        return [
            $this->indicator('trend_health', $trendHealthScore),
            $this->indicator('momentum_quality', $momentumQualityScore),
            $this->indicator('volatility_regime', $volatilityRegimeScore),
            $this->indicator('liquidity_pressure', $liquidityPressureScore),
            $this->indicator('breakout_readiness', $breakoutReadinessScore),
            $this->indicator('reversal_risk', $reversalRiskScore),
            $this->indicator('market_efficiency', $marketEfficiencyScore),
            $this->indicator('volatility_pressure', $volatilityPressureScore),
            $this->indicator('trend_exhaustion', $trendExhaustionScore),
            $this->indicator('mean_reversion_edge', $meanReversionEdgeScore),
        ];
    }
    /**
     * @return array{key: string, score: int, status: string}
     */
    private function indicator(string $key, int $score): array
    {
        return [
            'key' => $key,
            'score' => max(0, min(100, $score)),
            'status' => $this->statusByScore($score),
        ];
    }

    private function statusByScore(int $score): string
    {
        if ($score >= 70) {
            return 'strong';
        }

        if ($score >= 45) {
            return 'balanced';
        }

        return 'weak';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function emptyPayload(): array
    {
        return [
            'blackline' => [
                'score' => 0,
                'trend' => 0,
                'momentum' => 0,
                'volatility' => 0,
                'volume' => 0,
                'risk' => 0,
                'bias' => 'NEUTRAL',
            ],
            'regime' => [
                'state' => 'Range',
                'confidence' => 0,
                'shiftProbability' => 0,
                'nextStates' => [
                    ['name' => 'Trend', 'probability' => 0],
                    ['name' => 'Range', 'probability' => 0],
                    ['name' => 'Compression', 'probability' => 0],
                    ['name' => 'Expansion', 'probability' => 0],
                ],
            ],
            'customIndicators' => [
                ['key' => 'trend_health', 'score' => 0, 'status' => 'weak'],
                ['key' => 'momentum_quality', 'score' => 0, 'status' => 'weak'],
                ['key' => 'volatility_regime', 'score' => 0, 'status' => 'weak'],
                ['key' => 'liquidity_pressure', 'score' => 0, 'status' => 'weak'],
                ['key' => 'breakout_readiness', 'score' => 0, 'status' => 'weak'],
                ['key' => 'reversal_risk', 'score' => 0, 'status' => 'weak'],
                ['key' => 'market_efficiency', 'score' => 0, 'status' => 'weak'],
                ['key' => 'volatility_pressure', 'score' => 0, 'status' => 'weak'],
                ['key' => 'trend_exhaustion', 'score' => 0, 'status' => 'weak'],
                ['key' => 'mean_reversion_edge', 'score' => 0, 'status' => 'weak'],
            ],
        ];
    }

    /**
     * @param array<int, float> $series
     */
    private function latestFinite(array $series, ?float $fallback = null): ?float
    {
        for ($i = count($series) - 1; $i >= 0; $i -= 1) {
            $value = $series[$i] ?? NAN;
            if (is_finite($value)) {
                return (float) $value;
            }
        }

        return $fallback;
    }

    /**
     * @param array<int, array{adx: float, plusDI: float, minusDI: float}> $adxSeries
     * @return array{adx: float, plusDI: float, minusDI: float}|null
     */
    private function latestAdx(array $adxSeries): ?array
    {
        for ($i = count($adxSeries) - 1; $i >= 0; $i -= 1) {
            $item = $adxSeries[$i] ?? null;
            if (!is_array($item)) {
                continue;
            }

            if (is_finite((float) $item['adx'])) {
                return [
                    'adx' => (float) $item['adx'],
                    'plusDI' => (float) $item['plusDI'],
                    'minusDI' => (float) $item['minusDI'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array<int, array{adx: float, plusDI: float, minusDI: float}> $adxSeries
     */
    private function latestAdxSlope(array $adxSeries, int $lookback = 6): float
    {
        $points = [];

        for ($i = count($adxSeries) - 1; $i >= 0; $i -= 1) {
            $item = $adxSeries[$i] ?? null;
            if (!is_array($item)) {
                continue;
            }

            $adx = (float) $item['adx'];
            if (!is_finite($adx)) {
                continue;
            }

            array_unshift($points, $adx);
            if (count($points) >= $lookback) {
                break;
            }
        }

        if (count($points) < 2) {
            return 0.0;
        }

        $first = $points[0];
        $last = $points[count($points) - 1];

        return ($last - $first) / max(1, count($points) - 1);
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function averageBodyRatio(array $candles): float
    {
        if ($candles === []) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;

        foreach ($candles as $candle) {
            $range = max(0.0000001, $candle->range());
            $sum += $candle->body() / $range;
            $count += 1;
        }

        return $count > 0 ? ($sum / $count) : 0.0;
    }

    /**
     * @param array<int, Candle> $candles
     */
    private function averageWickRatio(array $candles): float
    {
        if ($candles === []) {
            return 0.0;
        }

        $sum = 0.0;
        $count = 0;

        foreach ($candles as $candle) {
            $range = max(0.0000001, $candle->range());
            $wick = max(0.0, $range - $candle->body());
            $sum += $wick / $range;
            $count += 1;
        }

        return $count > 0 ? ($sum / $count) : 0.0;
    }

    /**
     * @param array<int, float> $close
     */
    private function trendConsistency(array $close, int $lookback = 20): float
    {
        if (count($close) <= $lookback) {
            return 0.0;
        }

        $slice = array_slice($close, -($lookback + 1));
        $moves = 0;
        $sameDirection = 0;
        $prevSign = 0;

        for ($i = 1; $i < count($slice); $i += 1) {
            $delta = (float) $slice[$i] - (float) $slice[$i - 1];
            if (abs($delta) < 0.0000001) {
                continue;
            }

            $sign = $delta > 0 ? 1 : -1;
            if ($prevSign !== 0 && $sign === $prevSign) {
                $sameDirection += 1;
            }

            $prevSign = $sign;
            $moves += 1;
        }

        if ($moves <= 1) {
            return 0.0;
        }

        return $sameDirection / ($moves - 1);
    }

    /**
     * @param array<int, float> $close
     */
    private function directionalEfficiency(array $close, int $lookback = 20): float
    {
        if (count($close) <= $lookback) {
            return 0.0;
        }

        $slice = array_slice($close, -($lookback + 1));
        $start = (float) $slice[0];
        $end = (float) $slice[count($slice) - 1];

        $path = 0.0;
        for ($i = 1; $i < count($slice); $i += 1) {
            $path += abs((float) $slice[$i] - (float) $slice[$i - 1]);
        }

        if ($path <= 0.0000001) {
            return 0.0;
        }

        return $this->clamp(abs($end - $start) / $path, 0.0, 1.0);
    }

    /**
     * @param array<int, float> $close
     */
    private function analyticsCacheTtlSeconds(string $interval): int
    {
        $configured = max(5, (int) config('terminal.chart_cache_seconds', 120));

        $maxByInterval = match (strtolower($interval)) {
            '15m' => 10,
            '1h' => 20,
            '4h' => 30,
            '1d' => 60,
            default => 20,
        };

        return max(5, min($configured, $maxByInterval));
    }

    private function percentChange(array $close, int $lookback): float
    {
        $n = count($close);
        if ($n <= $lookback) {
            return 0.0;
        }

        $from = (float) $close[$n - 1 - $lookback];
        $to = (float) $close[$n - 1];

        if ($from == 0.0) {
            return 0.0;
        }

        return (($to - $from) / $from) * 100.0;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function bandScore(float $value, float $hardLow, float $idealLow, float $idealHigh, float $hardHigh): float
    {
        if ($value <= $hardLow || $value >= $hardHigh) {
            return 0.0;
        }

        if ($value >= $idealLow && $value <= $idealHigh) {
            return 100.0;
        }

        if ($value < $idealLow) {
            return $this->clamp((($value - $hardLow) / ($idealLow - $hardLow)) * 100.0, 0.0, 100.0);
        }

        return $this->clamp((($hardHigh - $value) / ($hardHigh - $idealHigh)) * 100.0, 0.0, 100.0);
    }

    /**
     * @param array<int, float> $close
     */
    private function bollingerWidth(array $close, int $period = 20, float $multiplier = 2.0): float
    {
        if (count($close) < $period) {
            return 0.0;
        }

        $window = array_slice($close, -$period);
        $mean = array_sum($window) / $period;

        if ($mean <= 0.0) {
            return 0.0;
        }

        $variance = 0.0;
        foreach ($window as $value) {
            $variance += ($value - $mean) ** 2;
        }

        $std = sqrt($variance / $period);
        $upper = $mean + ($multiplier * $std);
        $lower = $mean - ($multiplier * $std);

        return (($upper - $lower) / $mean) * 100.0;
    }

    /**
     * @param array<int, float> $close
     * @return array<int, float>
     */
    private function bollingerWidthSeries(array $close, int $period = 20, float $multiplier = 2.0): array
    {
        $result = [];

        for ($i = $period; $i <= count($close); $i += 1) {
            $slice = array_slice($close, 0, $i);
            $result[] = $this->bollingerWidth($slice, $period, $multiplier);
        }

        return $result;
    }

    /**
     * @param array<int, float> $values
     */
    private function median(array $values): float
    {
        $values = array_values(array_filter($values, static fn (mixed $v): bool => is_finite((float) $v)));
        $count = count($values);
        if ($count === 0) {
            return 0.0;
        }

        sort($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return (float) $values[$middle];
        }

        return ((float) $values[$middle - 1] + (float) $values[$middle]) / 2;
    }

    /**
     * @return array<int, array{name: string, probability: int}>
     */
    private function nextStateProbabilities(string $state, int $shiftProbability): array
    {
        $stay = 100 - $shiftProbability;

        return match ($state) {
            'Trend' => [
                ['name' => 'Trend', 'probability' => (int) round($stay * 0.9)],
                ['name' => 'Range', 'probability' => (int) round($shiftProbability * 0.45)],
                ['name' => 'Compression', 'probability' => (int) round($shiftProbability * 0.20)],
                ['name' => 'Expansion', 'probability' => (int) round($shiftProbability * 0.35)],
            ],
            'Range' => [
                ['name' => 'Range', 'probability' => (int) round($stay * 0.9)],
                ['name' => 'Trend', 'probability' => (int) round($shiftProbability * 0.45)],
                ['name' => 'Compression', 'probability' => (int) round($shiftProbability * 0.30)],
                ['name' => 'Expansion', 'probability' => (int) round($shiftProbability * 0.25)],
            ],
            'Compression' => [
                ['name' => 'Compression', 'probability' => (int) round($stay * 0.8)],
                ['name' => 'Expansion', 'probability' => (int) round($shiftProbability * 0.55)],
                ['name' => 'Trend', 'probability' => (int) round($shiftProbability * 0.30)],
                ['name' => 'Range', 'probability' => (int) round($shiftProbability * 0.15)],
            ],
            default => [
                ['name' => 'Expansion', 'probability' => (int) round($stay * 0.82)],
                ['name' => 'Trend', 'probability' => (int) round($shiftProbability * 0.45)],
                ['name' => 'Range', 'probability' => (int) round($shiftProbability * 0.35)],
                ['name' => 'Compression', 'probability' => (int) round($shiftProbability * 0.20)],
            ],
        };
    }
}
