<?php

namespace App\Services\Signals;

use App\DTOs\Trading\Candle;
use App\Models\Signal;
use App\Services\MarketData\MarketDataService;
use Illuminate\Support\Facades\Cache;

class SignalChartService
{
    public function __construct(
        private readonly MarketDataService $marketData,
    ) {}

    /**
     * @return array{candles: array<int, array<string, float|int>>, levels: array<string, float|null>}
     */
    public function buildForSignal(Signal $signal, int $limit = 200): array
    {
        $cacheKey = sprintf(
            'signal_chart:%s:%s:%s',
            $signal->id,
            $signal->updated_at?->timestamp ?? 0,
            $limit,
        );

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($signal, $limit) {
            $candles = $this->marketData->fetchKlines(
                symbol: $signal->symbol,
                interval: $signal->timeframe,
                limit: $limit,
                provider: $signal->exchange,
            );

            return [
                'candles' => $this->mapCandles($candles),
                'levels' => $this->buildLevels($signal),
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
     * @return array<string, float|null>
     */
    private function buildLevels(Signal $signal): array
    {
        $targets = $signal->takeProfitTargets();

        return [
            'entry' => $this->toFloat($signal->entry_price),
            'stop' => $this->toFloat($signal->stop_loss),
            'tp1' => $targets ? $targets[0] : $this->toFloat($signal->take_profit),
            'tp2' => $targets ? $targets[1] : $this->toFloat($signal->take_profit),
            'close' => $this->toFloat($signal->close_price),
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $float = (float) $value;

        return $float !== 0.0 ? $float : null;
    }
}
