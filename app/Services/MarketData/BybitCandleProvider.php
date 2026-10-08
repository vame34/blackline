<?php

namespace App\Services\MarketData;

use App\DTOs\Trading\Candle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BybitCandleProvider implements CandleProvider
{
    private string $baseUrl;
    private string $category;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('market_data.bybit.base_url'), '/');
        $this->category = (string) config('market_data.bybit.category', 'linear');
    }

    public function name(): string
    {
        return 'bybit';
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
    ): array {
        $mappedInterval = $this->mapInterval($interval);
        if ($mappedInterval === null) {
            Log::warning('bybit.interval_unsupported', ['interval' => $interval]);
            return [];
        }

        $limit = max(1, min($limit, 1000));

        $query = [
            'category' => $this->category,
            'symbol' => strtoupper($symbol),
            'interval' => $mappedInterval,
            'limit' => $limit,
        ];
        if ($startTimeMs !== null) {
            $query['start'] = $startTimeMs;
        }
        if ($endTimeMs !== null) {
            $query['end'] = $endTimeMs;
        }

        $response = $this->http()->get($this->baseUrl.'/v5/market/kline', $query);
        if (!$response->ok()) {
            Log::warning('bybit.klines_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return [];
        }

        if (($payload['retCode'] ?? null) !== 0) {
            Log::warning('bybit.klines_error', [
                'retCode' => $payload['retCode'] ?? null,
                'retMsg' => $payload['retMsg'] ?? null,
            ]);
            return [];
        }

        $list = $payload['result']['list'] ?? null;
        if (!is_array($list)) {
            return [];
        }

        $candles = [];
        foreach ($list as $item) {
            if (!is_array($item) || count($item) < 6) {
                continue;
            }

            $candles[] = new Candle(
                openTimeMs: (int) $item[0],
                open: (float) $item[1],
                high: (float) $item[2],
                low: (float) $item[3],
                close: (float) $item[4],
                volume: (float) $item[5],
            );
        }

        usort($candles, fn (Candle $a, Candle $b) => $a->openTimeMs <=> $b->openTimeMs);

        return $candles;
    }

    private function mapInterval(string $interval): ?string
    {
        return match ($interval) {
            '1m' => '1',
            '3m' => '3',
            '5m' => '5',
            '15m' => '15',
            '30m' => '30',
            '1h' => '60',
            '2h' => '120',
            '4h' => '240',
            '6h' => '360',
            '12h' => '720',
            '1d' => 'D',
            '1w' => 'W',
            default => null,
        };
    }

    private function http(): PendingRequest
    {
        return Http::timeout(10);
    }
}
