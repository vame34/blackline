<?php

namespace App\DTOs\Trading;

final readonly class InstrumentData
{
    /**
     * @param array<int, Candle> $candles
     * @param array<int, Candle> $contextCandles
     */
    public function __construct(
        public string $symbol,
        public string $exchange,
        public string $interval,
        public array $candles,
        public array $contextCandles,
        public float $contextVolumeMultiplier = 0.0,
        public float $priceStep = 0.0,
        public float $lotSize = 0.0,
    ) {}
}

