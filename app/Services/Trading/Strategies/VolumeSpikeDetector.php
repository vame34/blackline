<?php

namespace App\Services\Trading\Strategies;

use App\DTOs\Trading\Candle;
use App\DTOs\Trading\SpikeArea;

final readonly class VolumeSpikeDetector
{
    public function __construct(
        public float $multiplier = 2.0,
        public int $window = 20,
    ) {}

    /**
     * @param array<int, Candle> $candles
     * @param array<int, float> $volumeSma
     * @return array<int, SpikeArea>
     */
    public function detect(array $candles, array $volumeSma): array
    {
        $spikes = [];

        foreach ($candles as $i => $candle) {
            $baseline = $volumeSma[$i] ?? NAN;
            if (!is_finite($baseline) || $baseline == 0.0) {
                continue;
            }

            if ($candle->volume >= $baseline * $this->multiplier) {
                $spikes[] = new SpikeArea(
                    index: $i,
                    level: $candle->hl2(),
                    direction: $candle->bodyDirection(),
                    volume: $candle->volume,
                    timestampMs: $candle->openTimeMs,
                );
            }
        }

        return $spikes;
    }
}

