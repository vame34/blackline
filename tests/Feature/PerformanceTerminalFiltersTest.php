<?php

namespace Tests\Feature;

use App\Livewire\Performance;
use App\Models\PerformanceSnapshot;
use App\Services\Api\RiskManagementProfileService;
use App\Services\Performance\TerminalBacktestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PerformanceTerminalFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_symbol_and_leverage_switch_uses_different_precomputed_snapshots(): void
    {
        config()->set('terminal.instruments', [
            ['symbol' => 'BTCUSDT', 'label' => 'Bitcoin'],
            ['symbol' => 'RUNEUSDT', 'label' => 'THORChain'],
        ]);

        $this->seedSnapshot('portfolio:lev:10', 10.0, 120);
        $this->seedSnapshot('symbol:BTCUSDT:lev:10', 30.0, 70);
        $this->seedSnapshot('symbol:RUNEUSDT:lev:10', -5.0, 45);
        $this->seedSnapshot('symbol:BTCUSDT:lev:3', 5.0, 40);

        Livewire::test(Performance::class)
            ->assertSet('symbol', '__ALL__')
            ->assertSet('leverage', '10')
            ->assertViewHas('simulation', fn (array $simulation) => $this->near((float) $simulation['roi_pct'], 10.0))
            ->set('symbols', ['BTCUSDT'])
            ->assertViewHas('simulation', fn (array $simulation) => $this->near((float) $simulation['roi_pct'], 30.0))
            ->set('leverage', '3')
            ->assertViewHas('simulation', fn (array $simulation) => $this->near((float) $simulation['roi_pct'], 5.0));
    }

    public function test_capital_change_recalculates_final_balance_from_precomputed_roi(): void
    {
        config()->set('terminal.instruments', [
            ['symbol' => 'BTCUSDT', 'label' => 'Bitcoin'],
        ]);

        $this->seedSnapshot('symbol:BTCUSDT:lev:10', 12.5, 50);

        Livewire::test(Performance::class)
            ->set('symbols', ['BTCUSDT'])
            ->set('capital', '20000')
            ->assertViewHas('simulation', fn (array $simulation) => $this->near((float) $simulation['final_equity'], 22500.0));
    }

    public function test_history_progress_pattern_is_rendered_on_history_section(): void
    {
        config()->set('terminal.instruments', [
            ['symbol' => 'BTCUSDT', 'label' => 'Bitcoin'],
        ]);

        $this->seedSnapshot('portfolio:lev:10', 10.0, 120);

        Livewire::test(Performance::class)
            ->assertSeeHtml('data-history-progress-pattern=');
    }

    public function test_obsolete_profitable_snapshot_is_not_used(): void
    {
        config()->set('terminal.instruments', [['symbol' => 'BTCUSDT', 'label' => 'Bitcoin']]);
        $this->seedSnapshot('portfolio:lev:10', 999, 120);
        $snapshot = PerformanceSnapshot::query()->firstOrFail();
        $snapshot->update(['metrics' => array_merge($snapshot->metrics, ['model_version' => 1])]);

        Livewire::test(Performance::class)
            ->assertViewHas('simulation', fn (array $simulation) => $this->near((float) $simulation['roi_pct'], 0))
            ->assertSee(__('custom.performance.drawdown_limitations', config('trading.portfolio_risk')));
    }

    private function seedSnapshot(string $scopeKey, float $roiPct, int $closed): void
    {
        $wins = (int) floor($closed * 0.55);
        $losses = max(0, $closed - $wins);

        PerformanceSnapshot::query()->create([
            'scope' => 'terminal_backtest',
            'scope_key' => 'strategy:smart_volume_v1:'.$scopeKey.':days:365',
            'period_start' => now()->subDays(365),
            'period_end' => now(),
            'metrics' => [
                'model_version' => TerminalBacktestService::MODEL_VERSION,
                'strategy_key' => 'smart_volume_v1',
                'risk_model_version' => app(RiskManagementProfileService::class)->version(),
                'base_initial_capital' => 10000.0,
                'period_days' => 365,
                'roi_pct' => $roiPct,
                'max_drawdown_pct' => 12.34,
                'opened' => $closed,
                'closed' => $closed,
                'wins' => $wins,
                'losses' => $losses,
                'flats' => 0,
                'skipped_no_capital' => 0,
                'skipped_invalid' => 0,
                'total_entry_fees' => 100.0,
                'total_exit_fees' => 100.0,
                'avg_trade_pct' => 0.42,
                'profit_factor' => 1.6,
                'win_rate' => $closed > 0 ? ($wins / $closed) : null,
                'avg_r' => 0.42,
                'equity_curve' => [10000.0, 10000.0 * (1.0 + ($roiPct / 100.0))],
            ],
        ]);
    }

    private function near(float $actual, float $expected): bool
    {
        return abs($actual - $expected) < 0.0001;
    }
}
