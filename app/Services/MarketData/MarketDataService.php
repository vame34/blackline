<?php

namespace App\Services\MarketData;

use App\DTOs\Trading\Candle;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    /**
     * @var array<string, CandleProvider>
     */
    private array $providers;

    /**
     * @param array<string, CandleProvider> $providers
     */
    public function __construct(array $providers = [])
    {
        $this->providers = $providers ?: [
            'binance' => new BinanceCandleProvider(),
            'bybit' => new BybitCandleProvider(),
        ];
    }

    /**
     * @return array<int, Candle>
     */
    public function fetchKlines(
        string $symbol,
        string $interval,
        int $limit = 500,
        ?int $startTimeMs = null,
        ?int $endTimeMs = null,
        ?string $provider = null,
        ?array $fallbacks = null,
    ): array {
        $order = $this->resolveProviderOrder($provider, $fallbacks);

        foreach ($order as $providerName) {
            $instance = $this->providers[$providerName] ?? null;
            if (!$instance) {
                continue;
            }

            try {
                $candles = $instance->fetchKlines(
                    symbol: $symbol,
                    interval: $interval,
                    limit: $limit,
                    startTimeMs: $startTimeMs,
                    endTimeMs: $endTimeMs,
                );
            } catch (\Throwable $e) {
                Log::warning('market_data.provider_fetch_failed', [
                    'provider' => $providerName,
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if ($candles !== []) {
                return $candles;
            }
        }

        return [];
    }

    /**
     * Fetches the full candle range by paging provider requests (limit=1000 each call).
     *
     * @return array<int, Candle>
     */
    public function fetchKlinesRange(
        string $symbol,
        string $interval,
        ?int $startTimeMs,
        ?int $endTimeMs,
        ?string $provider = null,
        ?array $fallbacks = null,
        int $limitPerRequest = 1000,
    ): array {
        if ($startTimeMs === null || $endTimeMs === null || $startTimeMs >= $endTimeMs) {
            return $this->fetchKlines(
                symbol: $symbol,
                interval: $interval,
                limit: $limitPerRequest,
                startTimeMs: $startTimeMs,
                endTimeMs: $endTimeMs,
                provider: $provider,
                fallbacks: $fallbacks,
            );
        }

        $intervalMs = $this->intervalToMs($interval);
        if ($intervalMs <= 0) {
            return [];
        }

        $order = $this->resolveProviderOrder($provider, $fallbacks);
        foreach ($order as $providerName) {
            $instance = $this->providers[$providerName] ?? null;
            if (!$instance) {
                continue;
            }

            try {
                $cursor = $startTimeMs;
                $limit = max(1, min($limitPerRequest, 1000));
                $maxIterations = (int) ceil((($endTimeMs - $startTimeMs) / $intervalMs) / $limit) + 10;
                $iterations = 0;
                $rows = [];
                $seen = [];

                while ($cursor < $endTimeMs) {
                    $iterations += 1;
                    if ($iterations > $maxIterations) {
                        break;
                    }

                    $chunk = $instance->fetchKlines(
                        symbol: $symbol,
                        interval: $interval,
                        limit: $limit,
                        startTimeMs: $cursor,
                        endTimeMs: $endTimeMs,
                    );

                    if ($chunk === []) {
                        break;
                    }

                    $lastOpenTime = null;
                    foreach ($chunk as $candle) {
                        $openTime = $candle->openTimeMs;
                        if ($openTime < $startTimeMs || $openTime >= $endTimeMs) {
                            continue;
                        }
                        if (isset($seen[$openTime])) {
                            continue;
                        }
                        $seen[$openTime] = true;
                        $rows[] = $candle;
                        $lastOpenTime = $openTime;
                    }

                    if ($lastOpenTime === null) {
                        break;
                    }

                    $next = max($cursor + $intervalMs, $lastOpenTime + $intervalMs);
                    if ($next <= $cursor) {
                        break;
                    }
                    $cursor = $next;
                }

                if ($rows !== []) {
                    usort($rows, static fn (Candle $a, Candle $b): int => $a->openTimeMs <=> $b->openTimeMs);
                    return $rows;
                }
            } catch (\Throwable $e) {
                Log::warning('market_data.provider_range_fetch_failed', [
                    'provider' => $providerName,
                    'symbol' => strtoupper($symbol),
                    'interval' => $interval,
                    'start_time_ms' => $startTimeMs,
                    'end_time_ms' => $endTimeMs,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function resolveProviderOrder(?string $provider, ?array $fallbacks): array
    {
        $defaultProvider = (string) config('market_data.default_provider', 'bybit');
        $configuredFallbacks = (array) config('market_data.fallback_providers', ['binance']);

        $order = [];
        if ($provider) {
            $order[] = $provider;
        } else {
            $order[] = $defaultProvider;
        }

        if ($fallbacks !== null) {
            $order = array_merge($order, $fallbacks);
        } else {
            $order = array_merge($order, $configuredFallbacks);
        }

        $order = array_values(array_unique(array_filter($order)));

        return $order;
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
