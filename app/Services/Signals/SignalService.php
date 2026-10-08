<?php

namespace App\Services\Signals;

use App\DTOs\Signals\SignalListFilters;
use App\Models\Signal;
use App\Repositories\Signals\SignalRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class SignalService
{
    public function __construct(
        private readonly SignalRepository $signals,
    ) {}

    public function paginatePublishedForCabinet(SignalListFilters $filters, int $perPage = 20): LengthAwarePaginator
    {
        return $this->signals->paginatePublished($filters, $perPage);
    }

    /**
     * @return array{total_trades: int, wins: int, losses: int, win_rate: ?float, sum_pct: float}
     */
    public function summarizeClosedPerformance(SignalListFilters $filters, int $days = 365): array
    {
        return $this->signals->summarizeClosedPerformance($filters, $days);
    }

    public function getPublishedOrFail(int $id): Signal
    {
        $signal = $this->signals->findPublishedById($id);
        if (!$signal) {
            abort(404);
        }
        return $signal;
    }

    /**
     * @return Collection<int, Signal>
     */
    public function listForDashboard(int $limit = 80): Collection
    {
        return $this->signals->listForDashboard($limit);
    }
}
