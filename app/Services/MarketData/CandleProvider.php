<?php

namespace App\Services\MarketData;

use App\DTOs\Trading\Candle;

interface CandleProvider
{
    public function name(): string;

    /**
     * @return array<int, Candle>
     */
    public function fetchKlines(
        string $symbol,
        string $interval,
        int $limit = 500,
        ?int $startTimeMs = null,
        ?int $endTimeMs = null,
    ): array;
}
