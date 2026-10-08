<?php

namespace App\DTOs\Trading;

final readonly class Candle
{
    public function __construct(
        public int $openTimeMs,
        public float $open,
        public float $high,
        public float $low,
        public float $close,
        public float $volume,
    ) {}

    public static function fromArray(array $payload): self
    {
        $openTime = $payload['openTime'] ?? $payload['openTimeMs'] ?? $payload[0] ?? 0;
        $open = $payload['open'] ?? $payload[1] ?? 0;
        $high = $payload['high'] ?? $payload[2] ?? 0;
        $low = $payload['low'] ?? $payload[3] ?? 0;
        $close = $payload['close'] ?? $payload[4] ?? 0;
        $volume = $payload['volume'] ?? $payload[5] ?? 0;

        return new self(
            openTimeMs: (int) $openTime,
            open: (float) $open,
            high: (float) $high,
            low: (float) $low,
            close: (float) $close,
            volume: (float) $volume,
        );
    }

    public function hl2(): float
    {
        return ($this->high + $this->low) / 2;
    }

    public function bodyDirection(): string
    {
        if ($this->close > $this->open) {
            return 'bullish';
        }
        if ($this->close < $this->open) {
            return 'bearish';
        }

        return 'neutral';
    }

    public function range(): float
    {
        return $this->high - $this->low;
    }

    public function body(): float
    {
        return abs($this->close - $this->open);
    }
}
