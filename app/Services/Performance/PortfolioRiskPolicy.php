<?php

namespace App\Services\Performance;

/** Limits planned stop losses; market gaps can still exceed the budget. */
final class PortfolioRiskPolicy
{
    public function settings(): array
    {
        return (array) config('trading.portfolio_risk');
    }

    public function riskPercent(float $ladderPercent, float $equity, float $peak): float
    {
        $settings = $this->settings();
        $risk = min($ladderPercent, (float) $settings['trade_percent']);
        if ($peak > 0 && 100 * (1 - $equity / $peak) >= $settings['reduce_at_drawdown_percent']) {
            $risk *= .5;
        }

        return max(0, $risk);
    }

    public function shouldHalt(float $equity, float $peak): bool
    {
        return $peak > 0 && 100 * (1 - $equity / $peak) >= $this->settings()['halt_at_drawdown_percent'];
    }

    public function rejectionReason(array $positions, string $symbol, string $direction, float $equity, float $budget): ?string
    {
        $total = $sameDirection = 0.0;
        foreach ($positions as $position) {
            if ($position['symbol'] === $symbol) {
                return 'duplicate_symbol';
            }
            $total += $position['reserved_risk'];
            if ($position['direction'] === $direction) {
                $sameDirection += $position['reserved_risk'];
            }
        }
        if ($total + $budget > $equity * $this->settings()['total_percent'] / 100 + 1e-9) {
            return 'portfolio_risk';
        }
        if ($sameDirection + $budget > $equity * $this->settings()['direction_percent'] / 100 + 1e-9) {
            return 'direction_risk';
        }

        return null;
    }
}
