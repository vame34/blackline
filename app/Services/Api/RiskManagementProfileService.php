<?php

namespace App\Services\Api;

final class RiskManagementProfileService
{
    /**
     * @return array{
     *   min_risk_percent: float,
     *   max_risk_percent: float,
     *   step_risk_percent: float,
     *   start_risk_percent: float
     * }
     */
    public function ladder(): array
    {
        $min = (float) config('trading.risk_ladder.min_percent', 0.5);
        $max = (float) config('trading.risk_ladder.max_percent', 2.0);
        $step = (float) config('trading.risk_ladder.step_percent', 0.1);
        $start = (float) config('trading.risk_ladder.start_percent', 1.0);

        return [
            'min_risk_percent' => max(0.1, round($min, 4)),
            'max_risk_percent' => max($min, round($max, 4)),
            'step_risk_percent' => max(0.01, round($step, 4)),
            'start_risk_percent' => round($start, 4),
        ];
    }

    /**
     * @return array{min:int,max:int}
     */
    public function leverageBounds(): array
    {
        $min = (int) config('trading.leverage.min', 1);
        $max = (int) config('trading.leverage.max', 20);

        return [
            'min' => max(1, $min),
            'max' => max(1, $max),
        ];
    }

    /**
     * Short payload returned together with signals and stream events.
     *
     * @return array<string, mixed>
     */
    public function realtimeSummary(): array
    {
        return [
            'model' => 'terminal_backtest_ladder',
            'version' => $this->version(),
            'risk_ladder' => $this->ladder(),
            'portfolio_risk' => (array) config('trading.portfolio_risk'),
            'entry_rules' => [
                'required_signal_fields' => ['symbol', 'direction', 'entry_price', 'stop_loss', 'take_profit'],
                'risk_update_after_close' => [
                    'win' => 'risk_level_up_by_one_step',
                    'loss' => 'risk_level_down_by_one_step',
                    'flat' => 'risk_level_unchanged',
                ],
                'no_entry_conditions' => [
                    'entry_or_stop_missing_or_non_positive',
                    'stop_distance_is_zero_or_invalid',
                    'insufficient_free_cash_for_margin',
                    'duplicate_symbol_position',
                    'portfolio_or_direction_risk_limit',
                    'drawdown_halt_until_end_of_simulation',
                ],
            ],
        ];
    }

    /**
     * Full money-management model for documentation and integrations.
     *
     * @return array<string, mixed>
     */
    public function fullModel(): array
    {
        $ladder = $this->ladder();
        $bounds = $this->leverageBounds();

        return [
            'model' => 'terminal_backtest_ladder',
            'version' => $this->version(),
            'scope' => 'money_management',
            'execution_assumptions' => $this->executionAssumptions(),
            'leverage_bounds' => $bounds,
            'risk_ladder' => $ladder,
            'portfolio_risk' => (array) config('trading.portfolio_risk'),
            'position_sizing' => [
                'risk_amount_formula' => 'risk_amount = equity * (risk_percent / 100)',
                'stop_percent_formula' => 'stop_percent = abs(entry_price - stop_loss) / entry_price',
                'notional_formula' => 'notional = risk_amount / ((stop_percent + exit_reserve * (1 + stop_percent)) * (1 + commission_rate) + 2 * commission_rate)',
                'exit_reserve_formula' => '(configured_stop_slippage_cap_bps + trade_slippage_bps + spread_bps) / 10000',
                'drawdown_basis' => 'realized_equity; excludes floating PnL and funding',
                'risk_cap_formula' => 'risk_percent = min(ladder_percent, portfolio_risk.trade_percent); halve at reduce_at_drawdown_percent',
                'margin_formula' => 'margin = notional / leverage',
                'entry_requirements' => [
                    'entry_price > 0',
                    'stop_loss > 0',
                    'stop_percent > 0',
                    'free_cash >= margin + entry_fee',
                ],
            ],
            'execution_notes' => [
                'Portfolio risk assumptions used by the backtester.',
                'Commissions and exchange execution specifics are broker-side and can differ from backtest.',
            ],
        ];
    }

    public function version(): string
    {
        $payload = [
            'ladder' => $this->ladder(),
            'leverage' => $this->leverageBounds(),
            'portfolio_risk' => (array) config('trading.portfolio_risk'),
            'accounting_version' => 3,
            'sizing_version' => 2,
            'execution' => $this->executionAssumptions(),
        ];

        return substr(hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES)), 0, 16);
    }

    private function executionAssumptions(): array
    {
        return array_intersect_key((array) config('trading'), array_flip([
            'commissionRate', 'stopSlippageBps', 'dynamicStopSlippageEnabled',
            'dynamicStopSlippageCapBps', 'tradeSlippageBps', 'spreadBps',
        ]));
    }
}
