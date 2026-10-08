<?php

namespace App\DTOs\Trading;

final readonly class StrategySignal
{
    public function __construct(
        public string $symbol,
        public string $exchange,
        public string $direction,
        public int $entryTimeMs,
        public float $entryPrice,
        public float $stopLoss,
        public float $takeProfit,
        public float $rewardRisk,
        public ?int $exitTimeMs = null,
        public ?float $exitPrice = null,
        public array $metadata = [],
    ) {
        if (! in_array($direction, ['LONG', 'SHORT'], true)
            || ! is_finite($entryPrice) || ! is_finite($stopLoss) || ! is_finite($takeProfit)
            || $entryPrice <= 0 || $stopLoss <= 0 || $takeProfit <= 0
            || ($direction === 'LONG' && ! ($stopLoss < $entryPrice && $entryPrice < $takeProfit))
            || ($direction === 'SHORT' && ! ($takeProfit < $entryPrice && $entryPrice < $stopLoss))
            || $entryTimeMs <= 0 || ! is_finite($rewardRisk) || $rewardRisk <= 0
            || (($exitTimeMs === null) !== ($exitPrice === null))
            || ($exitTimeMs !== null && ($exitTimeMs < $entryTimeMs || ! is_finite($exitPrice) || $exitPrice <= 0))) {
            throw new \InvalidArgumentException('Invalid strategy signal prices, direction or timestamps.');
        }
    }
}
