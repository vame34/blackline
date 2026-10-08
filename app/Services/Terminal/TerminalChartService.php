<?php

namespace App\Services\Terminal;

use App\DTOs\Trading\Candle;
use App\Enums\SignalDirection;
use App\Models\Signal;
use App\Repositories\Signals\SignalRepository;
use App\Services\MarketData\MarketDataService;
use App\Services\Trading\Indicators\Atr;
use App\Services\Trading\Indicators\Ema;
use App\Services\Trading\Strategies\VolumeStrategyConfig;
use Illuminate\Support\Facades\Cache;

class TerminalChartService
{
    public function __construct(
        private readonly MarketDataService $marketData,
        private readonly SignalRepository $signals,
    ) {}

    /**
     * @return array{
     *     candles: array<int, array<string, float|int>>,
     *     markers: array<int, array<string, mixed>>,
     *     indicators: array<string, array<int, array<string, float|int|string>>>
     * }
     */
    public function build(string $symbol, string $interval, int $limit = 240): array
    {
        $symbol = strtoupper($symbol);
        $provider = (string) config('terminal.exchange', 'bybit');

        $cacheKey = sprintf('terminal_chart:%s:%s:%s', $symbol, $interval, $limit);

        $cacheTtlSeconds = max(5, (int) config('terminal.chart_cache_seconds', 120));

        return Cache::remember($cacheKey, now()->addSeconds($cacheTtlSeconds), function () use ($symbol, $interval, $limit, $provider) {
            $candles = $this->marketData->fetchKlines(
                symbol: $symbol,
                interval: $interval,
                limit: $limit,
                provider: $provider,
            );

            $signals = $this->signals->listPublishedForTerminal($symbol, $interval, 150);

            return [
                'candles' => $this->mapCandles($candles),
                'markers' => $this->mapMarkers($signals),
                'indicators' => $this->buildIndicators($candles),
            ];
        });
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<int, array<string, float|int>>
     */
    private function mapCandles(array $candles): array
    {
        return array_values(array_map(
            static fn (Candle $candle) => [
                'time' => (int) floor($candle->openTimeMs / 1000),
                'open' => $candle->open,
                'high' => $candle->high,
                'low' => $candle->low,
                'close' => $candle->close,
            ],
            $candles,
        ));
    }

    /**
     * @param iterable<int, Signal> $signals
     * @return array<int, array<string, mixed>>
     */
    private function mapMarkers(iterable $signals): array
    {
        $markers = [];

        foreach ($signals as $signal) {
            $time = $signal->entry_at?->timestamp ?? $signal->published_at?->timestamp;
            if (!$time) {
                continue;
            }

            $isLong = $signal->direction === SignalDirection::Long;
            $markers[] = [
                'time' => $time,
                'position' => $isLong ? 'belowBar' : 'aboveBar',
                'shape' => $isLong ? 'arrowUp' : 'arrowDown',
                'color' => $isLong ? '#0f766e' : '#ef4444',
                'text' => $signal->status->value === 'closed' ? 'C' : 'S',
            ];
        }

        return $markers;
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<string, array<int, array<string, float|int|string>>>
     */
    private function buildIndicators(array $candles): array
    {
        if ($candles === []) {
            return [];
        }

        $config = VolumeStrategyConfig::fromArray((array) config('signals.volume_strategy', []));
        $closePrices = array_map(static fn (Candle $candle) => $candle->close, $candles);
        $timestamps = array_map(
            static fn (Candle $candle) => (int) floor($candle->openTimeMs / 1000),
            $candles,
        );

        return [
            'ema_fast' => $this->mapSeries($timestamps, Ema::calculate($closePrices, $config->entryFastPeriod)),
            'ema_mid' => $this->mapSeries($timestamps, Ema::calculate($closePrices, $config->entryMidPeriod)),
            'ema_slow' => $this->mapSeries($timestamps, Ema::calculate($closePrices, $config->entrySlowPeriod)),
            'atr' => $this->mapSeries($timestamps, Atr::calculate($candles, $config->atrPeriod)),
            'volume' => $this->mapVolumeSeries($candles),
        ];
    }

    /**
     * @param array<int, int> $timestamps
     * @param array<int, float> $values
     * @return array<int, array{time: int, value: float}>
     */
    private function mapSeries(array $timestamps, array $values): array
    {
        $series = [];

        foreach ($values as $index => $value) {
            if (!is_finite($value)) {
                continue;
            }

            $time = $timestamps[$index] ?? null;
            if (!$time) {
                continue;
            }

            $series[] = [
                'time' => $time,
                'value' => $value,
            ];
        }

        return $series;
    }

    /**
     * @param array<int, Candle> $candles
     * @return array<int, array{time: int, value: float, color: string}>
     */
    private function mapVolumeSeries(array $candles): array
    {
        $series = [];

        foreach ($candles as $candle) {
            $series[] = [
                'time' => (int) floor($candle->openTimeMs / 1000),
                'value' => $candle->volume,
                'color' => $candle->close >= $candle->open ? '#0f766e' : '#ef4444',
            ];
        }

        return $series;
    }
}
