<?php

namespace App\Services\Signals;

use App\DTOs\Trading\Candle;
use App\Enums\SignalDirection;
use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Services\MarketData\MarketDataService;
use App\Services\Trading\Utils\PriceStep;
use App\Services\Trading\Utils\Slippage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SignalAutoCloseService
{
    public function __construct(
        private readonly MarketDataService $marketData,
    ) {}

    /**
     * @param  array<int, string>|null  $symbols
     * @param  array<int, string>|null  $fallbacks
     * @return array{checked: int, closed: int, skipped_no_candles: int}
     */
    public function closeActiveSignals(
        ?array $symbols = null,
        ?string $timeframe = null,
        ?string $provider = null,
        ?array $fallbacks = null,
        ?string $exchange = null,
        bool $dryRun = false,
    ): array {
        $query = Signal::query()
            ->where('status', SignalStatus::Published)
            ->whereNull('closed_at')
            ->orderBy('id');

        if ($symbols !== null && $symbols !== []) {
            $query->whereIn('symbol', array_map('strtoupper', $symbols));
        }
        if (is_string($timeframe) && $timeframe !== '') {
            $query->where('timeframe', $timeframe);
        }
        if (is_string($exchange) && $exchange !== '') {
            $query->where('exchange', strtolower($exchange));
        }

        $checked = 0;
        $closed = 0;
        $skippedNoCandles = 0;
        $nowMs = ((int) Carbon::now('UTC')->getTimestamp()) * 1000;

        $active = $query->get();
        /** @var Collection<string, Collection<int, Signal>> $groups */
        $groups = $active->groupBy(
            static fn (Signal $signal): string => strtolower((string) $signal->exchange)
                .'|'.strtoupper((string) $signal->symbol)
                .'|'.strtolower((string) $signal->timeframe)
        );

        foreach ($groups as $groupKey => $signalsGroup) {
            /** @var Signal|null $first */
            $first = $signalsGroup->first();
            if (! $first) {
                continue;
            }

            $interval = strtolower((string) $first->timeframe);
            $intervalMs = $this->intervalToMs($interval);
            if ($intervalMs <= 0) {
                $checked += $signalsGroup->count();

                continue;
            }

            $earliestEntry = $signalsGroup
                ->pluck('entry_at')
                ->filter()
                ->sort()
                ->first();
            if (! $earliestEntry instanceof Carbon) {
                $checked += $signalsGroup->count();

                continue;
            }

            $resolvedProvider = $this->resolveProvider($provider, (string) $first->exchange);
            $startMs = ((int) $earliestEntry->getTimestamp()) * 1000;
            $endMs = $nowMs + $intervalMs;

            $candles = $this->marketData->fetchKlinesRange(
                symbol: (string) $first->symbol,
                interval: $interval,
                startTimeMs: $startMs,
                endTimeMs: $endMs,
                provider: $resolvedProvider,
                fallbacks: $fallbacks,
                limitPerRequest: 1000,
            );

            if ($candles === []) {
                $checked += $signalsGroup->count();
                $skippedNoCandles += $signalsGroup->count();

                continue;
            }

            $openTimes = array_map(
                static fn ($candle): int => (int) $candle->openTimeMs,
                $candles,
            );

            /** @var Signal $signal */
            foreach ($signalsGroup as $signal) {
                $checked += 1;

                if (! $signal->entry_at) {
                    continue;
                }

                $entryMs = ((int) $signal->entry_at->getTimestamp()) * 1000;
                $startIndex = $this->findFirstCandleIndexAtOrAfter($openTimes, $entryMs);
                if ($startIndex >= count($candles)) {
                    continue;
                }

                $hit = $this->detectExit($signal, $candles, $intervalMs, $startIndex);
                if ($hit === null) {
                    continue;
                }

                if ($dryRun) {
                    $closed += 1;

                    continue;
                }

                $signal->update([
                    'status' => SignalStatus::Closed,
                    'closed_at' => Carbon::createFromTimestampMs($hit['exitTimeMs'], 'UTC'),
                    'close_price' => $hit['exitPrice'],
                    'close_reason' => $hit['closeReason'],
                ]);
                $closed += 1;
            }
        }

        return [
            'checked' => $checked,
            'closed' => $closed,
            'skipped_no_candles' => $skippedNoCandles,
        ];
    }

    /**
     * @param  array<int, Candle>  $candles
     * @return array{exitPrice: float, exitTimeMs: int, closeReason: string}|null
     */
    private function detectExit(Signal $signal, array $candles, int $intervalMs, int $startIndex = 0): ?array
    {
        $direction = $signal->direction instanceof SignalDirection
            ? $signal->direction->value
            : (string) $signal->direction;
        $direction = strtoupper($direction);
        $stopLoss = (float) $signal->stop_loss;
        $takeProfit = (float) $signal->take_profit;
        $execution = (array) ($signal->strategy_params['execution'] ?? config('trading', []));
        $spreadBps = (float) ($execution['spreadBps'] ?? 0.0);
        $tradeSlippageBps = (float) ($execution['tradeSlippageBps'] ?? 0.0);
        $stopSlippageBps = (float) ($execution['stopSlippageBps'] ?? 0.0);
        $dynamicStopSlippageEnabled = (bool) ($execution['dynamicStopSlippageEnabled'] ?? false);
        $dynamicStopRangeTriggerBps = (float) ($execution['dynamicStopRangeTriggerBps'] ?? 80.0);
        $dynamicStopRangeCoeff = (float) ($execution['dynamicStopRangeCoeff'] ?? 0.35);
        $dynamicStopGapCoeff = (float) ($execution['dynamicStopGapCoeff'] ?? 0.5);
        $dynamicStopSlippageCapBps = (float) ($execution['dynamicStopSlippageCapBps'] ?? 120.0);
        $sameBarPolicy = strtolower((string) ($execution['sameCandleExit'] ?? 'stop'));

        for ($i = max(0, $startIndex); $i < count($candles); $i += 1) {
            $candle = $candles[$i];

            if ($direction === 'LONG') {
                $stopHit = $candle->low <= $stopLoss;
                $takeHit = $candle->close >= $takeProfit;

                if ($stopHit && $takeHit && $sameBarPolicy === 'take') {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'tp',
                    ];
                }
                if ($stopHit) {
                    $base = $candle->open <= $stopLoss ? $candle->open : $stopLoss;
                    $exitPrice = Slippage::applySpread($base, $direction, 'exit', $spreadBps);
                    $exitPrice = Slippage::applyTradeSlippage($exitPrice, $direction, 'exit', $tradeSlippageBps);
                    $rangeBps = max(0.0, (($candle->high - $candle->low) / max(1e-9, (float) $candle->close)) * 10_000);
                    $gapBps = Slippage::adverseStopGapBps($candle->open, $stopLoss, $direction);
                    $effectiveStopSlippageBps = Slippage::dynamicStopSlippageBps(
                        baseBps: $stopSlippageBps,
                        rangeBps: $rangeBps,
                        gapBps: $gapBps,
                        enabled: $dynamicStopSlippageEnabled,
                        rangeTriggerBps: $dynamicStopRangeTriggerBps,
                        rangeCoeff: $dynamicStopRangeCoeff,
                        gapCoeff: $dynamicStopGapCoeff,
                        capBps: $dynamicStopSlippageCapBps,
                    );
                    $exitPrice = Slippage::applyStopSlippage($exitPrice, $direction, $effectiveStopSlippageBps);

                    return [
                        'exitPrice' => PriceStep::applyStopPriceStep($exitPrice, $direction, 0.0),
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'sl',
                    ];
                }
                if ($takeHit) {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'tp',
                    ];
                }
            } else {
                $stopHit = $candle->high >= $stopLoss;
                $takeHit = $candle->close <= $takeProfit;

                if ($stopHit && $takeHit && $sameBarPolicy === 'take') {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'tp',
                    ];
                }
                if ($stopHit) {
                    $base = $candle->open >= $stopLoss ? $candle->open : $stopLoss;
                    $exitPrice = Slippage::applySpread($base, $direction, 'exit', $spreadBps);
                    $exitPrice = Slippage::applyTradeSlippage($exitPrice, $direction, 'exit', $tradeSlippageBps);
                    $rangeBps = max(0.0, (($candle->high - $candle->low) / max(1e-9, (float) $candle->close)) * 10_000);
                    $gapBps = Slippage::adverseStopGapBps($candle->open, $stopLoss, $direction);
                    $effectiveStopSlippageBps = Slippage::dynamicStopSlippageBps(
                        baseBps: $stopSlippageBps,
                        rangeBps: $rangeBps,
                        gapBps: $gapBps,
                        enabled: $dynamicStopSlippageEnabled,
                        rangeTriggerBps: $dynamicStopRangeTriggerBps,
                        rangeCoeff: $dynamicStopRangeCoeff,
                        gapCoeff: $dynamicStopGapCoeff,
                        capBps: $dynamicStopSlippageCapBps,
                    );
                    $exitPrice = Slippage::applyStopSlippage($exitPrice, $direction, $effectiveStopSlippageBps);

                    return [
                        'exitPrice' => PriceStep::applyStopPriceStep($exitPrice, $direction, 0.0),
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'sl',
                    ];
                }
                if ($takeHit) {
                    return [
                        'exitPrice' => $takeProfit,
                        'exitTimeMs' => (int) $candle->openTimeMs + $intervalMs,
                        'closeReason' => 'tp',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, int>  $openTimes
     */
    private function findFirstCandleIndexAtOrAfter(array $openTimes, int $entryMs): int
    {
        $low = 0;
        $high = count($openTimes) - 1;
        $result = count($openTimes);

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($openTimes[$mid] >= $entryMs) {
                $result = $mid;
                $high = $mid - 1;
            } else {
                $low = $mid + 1;
            }
        }

        return $result;
    }

    private function resolveProvider(?string $provider, string $exchange): ?string
    {
        if (is_string($provider) && $provider !== '') {
            return strtolower($provider);
        }

        $exchange = strtolower($exchange);
        if (in_array($exchange, ['binance', 'bybit'], true)) {
            return $exchange;
        }

        return (string) config('market_data.default_provider', 'binance');
    }

    private function intervalToMs(string $interval): int
    {
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
}
