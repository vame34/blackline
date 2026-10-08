<?php

namespace App\Repositories\Signals;

use App\DTOs\Signals\SignalListFilters;
use App\Enums\SignalStatus;
use App\Models\Signal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EloquentSignalRepository implements SignalRepository
{
    private const RESULT_PCT_EXPR = "COALESCE(result_pct, CASE WHEN entry_price IS NULL OR close_price IS NULL OR entry_price = 0 THEN NULL WHEN direction = 'LONG' THEN (close_price - entry_price) / entry_price WHEN direction = 'SHORT' THEN (entry_price - close_price) / entry_price ELSE NULL END)";

    public function paginatePublished(SignalListFilters $filters, int $perPage = 20): LengthAwarePaginator
    {
        $now = Carbon::now();

        $query = Signal::query()
            ->with(['category'])
            ->where('published_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            });

        if ($filters->status) {
            if ($filters->status === 'all') {
                $query->whereIn('status', [SignalStatus::Published, SignalStatus::Closed]);
            } else {
                $query->where('status', $filters->status);
            }
        } else {
            $query->where('status', SignalStatus::Published);
        }

        $this->applyCommonFilters($query, $filters);

        $sort = $filters->sort ?? 'recent';
        if (in_array($sort, ['best_r', 'worst_r'], true)) {
            $query->where('status', SignalStatus::Closed);
        }
        if ($sort === 'oldest') {
            $query->orderBy('published_at');
        } elseif ($sort === 'entry_new') {
            $query->orderByDesc('entry_at')->orderByDesc('published_at');
        } elseif ($sort === 'entry_old') {
            $query->orderBy('entry_at')->orderBy('published_at');
        } elseif ($sort === 'best_r') {
            $query->orderByRaw('result_r is null')->orderByDesc('result_r');
        } elseif ($sort === 'worst_r') {
            $query->orderByRaw('result_r is null')->orderBy('result_r');
        } else {
            $query->latest('published_at');
        }

        return $query->paginate($perPage);
    }

    public function summarizeClosedPerformance(SignalListFilters $filters, int $days = 365): array
    {
        $query = Signal::query()
            ->where('status', SignalStatus::Closed)
            ->whereNotNull('closed_at')
            ->where('closed_at', '>=', Carbon::now()->subDays($days))
            ->whereNotNull('entry_price')
            ->whereNotNull('close_price');

        $this->applyCommonFilters($query, $filters);

        $summary = $query
            ->selectRaw('COUNT(*) as total_trades')
            ->selectRaw('SUM(CASE WHEN ('.self::RESULT_PCT_EXPR.') > 0 THEN 1 ELSE 0 END) as wins')
            ->selectRaw('SUM(CASE WHEN ('.self::RESULT_PCT_EXPR.') < 0 THEN 1 ELSE 0 END) as losses')
            ->selectRaw('SUM(CASE WHEN ('.self::RESULT_PCT_EXPR.') IS NOT NULL THEN '.self::RESULT_PCT_EXPR.' ELSE 0 END) as sum_pct')
            ->first();

        $total = (int) ($summary?->total_trades ?? 0);
        $wins = (int) ($summary?->wins ?? 0);
        $losses = (int) ($summary?->losses ?? 0);
        $sumPct = (float) ($summary?->sum_pct ?? 0.0);

        return [
            'total_trades' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $total > 0 ? $wins / $total : null,
            'sum_pct' => $sumPct,
        ];
    }

    public function findPublishedById(int $id): ?Signal
    {
        $now = Carbon::now();

        return Signal::query()
            ->with(['category'])
            ->whereKey($id)
            ->where('status', SignalStatus::Published)
            ->where('published_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->first();
    }

    public function listPublishedForTerminal(string $symbol, string $timeframe, int $limit = 200): Collection
    {
        $now = Carbon::now();

        return Signal::query()
            ->where('symbol', strtoupper($symbol))
            ->where('timeframe', $timeframe)
            ->whereIn('status', [SignalStatus::Published, SignalStatus::Closed])
            ->where('published_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    public function listForDashboard(int $limit = 80): Collection
    {
        $now = Carbon::now();

        return Signal::query()
            ->select([
                'id',
                'symbol',
                'direction',
                'status',
                'entry_price',
                'published_at',
                'exchange',
            ])
            ->whereIn('status', [SignalStatus::Published, SignalStatus::Closed])
            ->where('published_at', '<=', $now)
            ->where(function ($query) use ($now) {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    private function applyCommonFilters(Builder $query, SignalListFilters $filters): void
    {
        if ($filters->strategyKey !== null && $filters->strategyKey !== '') {
            $query->where('strategy_key', $filters->strategyKey);
        }

        if ($filters->symbol) {
            $query->where('symbol', 'like', '%'.strtoupper($filters->symbol).'%');
        }
        if ($filters->direction) {
            $query->where('direction', strtoupper($filters->direction));
        }
        if ($filters->timeframe) {
            $query->where('timeframe', $filters->timeframe);
        }
        if ($filters->categoryId) {
            $query->where('signal_category_id', $filters->categoryId);
        }
        if ($filters->risk) {
            $expr = 'ABS(entry_price - stop_loss) / NULLIF(entry_price, 0)';
            if ($filters->risk === 'low') {
                $query->whereRaw($expr.' <= ?', [0.008]);
            } elseif ($filters->risk === 'medium') {
                $query->whereRaw($expr.' > ? AND '.$expr.' <= ?', [0.008, 0.015]);
            } elseif ($filters->risk === 'high') {
                $query->whereRaw($expr.' > ?', [0.015]);
            }
        }

        if ($filters->dateFrom && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters->dateFrom)) {
            $query->where('published_at', '>=', CarbonImmutable::createFromFormat('!Y-m-d', $filters->dateFrom, $filters->timezone)->utc());
        }

        if ($filters->dateTo && preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters->dateTo)) {
            $query->where('published_at', '<', CarbonImmutable::createFromFormat('!Y-m-d', $filters->dateTo, $filters->timezone)->addDay()->utc());
        }

        if ($filters->outcome === 'win') {
            $query->where('status', SignalStatus::Closed)
                ->whereRaw('('.self::RESULT_PCT_EXPR.') > 0');
        } elseif ($filters->outcome === 'loss') {
            $query->where('status', SignalStatus::Closed)
                ->whereRaw('('.self::RESULT_PCT_EXPR.') < 0');
        }
    }
}
