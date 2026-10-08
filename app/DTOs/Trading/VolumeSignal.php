<?php

namespace App\DTOs\Trading;

final readonly class VolumeSignal
{
    public function __construct(
        public string $symbol,
        public string $exchange,
        public string $direction, // LONG|SHORT
        public int $entryTimeMs,
        public float $entryPrice,
        public float $stopLoss,
        public float $takeProfit,
        public float $rewardRisk,
        public float $level,
        public ?int $exitTimeMs = null,
        public ?float $exitPrice = null,
    ) {}
}
