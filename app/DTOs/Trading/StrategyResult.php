<?php

namespace App\DTOs\Trading;

final readonly class StrategyResult
{
    /** @param array<int, StrategySignal> $signals */
    public function __construct(public array $signals, public array $debug = [])
    {
        foreach ($signals as $signal) {
            if (! $signal instanceof StrategySignal) {
                throw new \InvalidArgumentException('Adapters must return StrategySignal objects.');
            }
        }
    }
}
