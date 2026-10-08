<?php

namespace App\Services\Performance;

use App\Enums\SignalStatus;
use App\Models\Signal;
use Illuminate\Support\Collection;

class PerformanceService
{
    /**
     * @return array<string, mixed>
     */
    public function calculateGlobal(): array
    {
        $signals = Signal::query()
            ->where('status', SignalStatus::Closed)
            ->whereNotNull('close_price')
            ->orderBy('closed_at')
            ->get();

        return $this->calculateFromSignals($signals);
    }

    /**
     * @return Collection<int, Signal>
     */
    public function recentClosedSignals(int $limit = 10): Collection
    {
        return Signal::query()
            ->with(['category'])
            ->where('status', SignalStatus::Closed)
            ->whereNotNull('close_price')
            ->whereNotNull('closed_at')
            ->orderByDesc('closed_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param Collection<int, Signal> $signals
     * @return array<string, mixed>
     */
    public function calculateFromSignals(Collection $signals): array
    {
        $results = [];
        foreach ($signals as $signal) {
            $r = $signal->resultR();
            if ($r === null) {
                continue;
            }
            $results[] = $r;
        }

        $total = count($results);
        if ($total === 0) {
            return [
                'total_trades' => 0,
                'wins' => 0,
                'losses' => 0,
                'win_rate' => null,
                'avg_r' => null,
                'profit_factor' => null,
                'max_drawdown' => null,
                'equity_curve' => [],
            ];
        }

        $wins = 0;
        $losses = 0;
        $sum = 0.0;
        $sumPos = 0.0;
        $sumNeg = 0.0;

        foreach ($results as $r) {
            $sum += $r;
            if ($r > 0) {
                $wins += 1;
                $sumPos += $r;
            } elseif ($r < 0) {
                $losses += 1;
                $sumNeg += $r;
            }
        }

        $equity = 1.0;
        $peak = 1.0;
        $maxDrawdown = 0.0;
        $equityCurve = [];

        foreach ($results as $r) {
            $equity += $r;
            $peak = max($peak, $equity);
            $drawdown = $peak > 0 ? ($peak - $equity) / $peak : 0.0;
            $maxDrawdown = max($maxDrawdown, $drawdown);
            $equityCurve[] = $equity;
        }

        $profitFactor = $sumNeg < 0 ? $sumPos / abs($sumNeg) : null;

        return [
            'total_trades' => $total,
            'wins' => $wins,
            'losses' => $losses,
            'win_rate' => $total > 0 ? $wins / $total : null,
            'avg_r' => $sum / $total,
            'profit_factor' => $profitFactor,
            'max_drawdown' => $maxDrawdown,
            'equity_curve' => $equityCurve,
        ];
    }
}
