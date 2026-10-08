<?php

namespace App\DTOs\Signals;

final readonly class SignalListFilters
{
    public function __construct(
        public ?string $strategyKey = null,
        public ?string $symbol = null,
        public ?string $direction = null,
        public ?string $timeframe = null,
        public ?int $categoryId = null,
        public ?string $status = null,
        public ?string $risk = null,
        public ?string $sort = null,
        public ?string $dateFrom = null,
        public ?string $dateTo = null,
        public ?string $outcome = null,
        public string $timezone = 'UTC',
    ) {}
}
