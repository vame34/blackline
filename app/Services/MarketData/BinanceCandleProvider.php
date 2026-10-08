<?php

namespace App\Services\MarketData;

use App\DTOs\Trading\Candle;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BinanceCandleProvider implements CandleProvider
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('market_data.binance.base_url'), '/');
    }

    public function name(): string
    {
        return 'binance';
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
        $limit = max(1, min($limit, 1000));

        $query = [
            'symbol' => strtoupper($symbol),
            'interval' => $interval,
            'limit' => $limit,
        ];
        if ($startTimeMs !== null) {
            $query['startTime'] = $startTimeMs;
        }
        if ($endTimeMs !== null) {
            $query['endTime'] = $endTimeMs;
        }

        $response = $this->http()->get($this->baseUrl.'/api/v3/klines', $query);
        if (!$response->ok()) {
            Log::warning('binance.klines_failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            return [];
        }

        $candles = [];
        foreach ($payload as $item) {
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

        return $candles;
    }

    private function http(): PendingRequest
    {
        return Http::timeout(10);
    }
}
