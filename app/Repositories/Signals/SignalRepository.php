<?php

namespace App\Repositories\Signals;

use App\DTOs\Signals\SignalListFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface SignalRepository
{
    public function paginatePublished(SignalListFilters $filters, int $perPage = 20): LengthAwarePaginator;

    /**
     * @return array{total_trades: int, wins: int, losses: int, win_rate: ?float, sum_pct: float}
     */
    public function summarizeClosedPerformance(SignalListFilters $filters, int $days = 365): array;

    public function findPublishedById(int $id): ?\App\Models\Signal;

    /**
     * @return Collection<int, \App\Models\Signal>
     */
    public function listPublishedForTerminal(string $symbol, string $timeframe, int $limit = 200): Collection;

    /**
     * @return Collection<int, \App\Models\Signal>
     */
    public function listForDashboard(int $limit = 80): Collection;
}
