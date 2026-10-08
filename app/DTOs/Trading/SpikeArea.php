<?php

namespace App\DTOs\Trading;

final readonly class SpikeArea
{
    public function __construct(
        public int $index,
        public float $level,
        public string $direction, // bullish|bearish
        public float $volume,
        public int $timestampMs,
    ) {}
}

