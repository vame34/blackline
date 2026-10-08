<?php

namespace Tests\Unit;

use App\Models\Signal;
use App\Services\Performance\PortfolioRiskPolicy;
use App\Services\Performance\TerminalBacktestService;
use Tests\TestCase;

class PortfolioRiskPolicyTest extends TestCase
{
    public function test_correlated_positions_share_a_budget_and_duplicates_are_rejected(): void
    {
        $policy = app(PortfolioRiskPolicy::class);
        $positions = [
            ['symbol' => 'BTCUSDT', 'direction' => 'LONG', 'reserved_risk' => 75],
            ['symbol' => 'ETHUSDT', 'direction' => 'LONG', 'reserved_risk' => 75],
        ];
        $this->assertSame('duplicate_symbol', $policy->rejectionReason($positions, 'BTCUSDT', 'SHORT', 10000, 50));
        $this->assertSame('direction_risk', $policy->rejectionReason($positions, 'SOLUSDT', 'LONG', 10000, 50));
        $this->assertNull($policy->rejectionReason($positions, 'SOLUSDT', 'SHORT', 10000, 50));
        $this->assertSame('portfolio_risk', $policy->rejectionReason($positions, 'SOLUSDT', 'SHORT', 10000, 51));
        $this->assertEquals(.5, $policy->riskPercent(2, 10000, 10000));
        $this->assertEquals(.5, $policy->riskPercent(2, 9000, 10000));
        $this->assertEquals(.25, $policy->riskPercent(2, 7900, 10000));
        $this->assertTrue($policy->shouldHalt(6700, 10000));
        $this->assertFalse($policy->shouldHalt(6900, 10000));
    }

    public function test_drawdown_halt_does_not_discard_existing_exits(): void
    {
        config(['trading.portfolio_risk.halt_at_drawdown_percent' => .1]);
        $signals = collect([
            $this->signal(1, 'BTCUSDT', '00:00', '01:00', 90),
            $this->signal(2, 'ETHUSDT', '00:00', '02:00', 105),
            $this->signal(3, 'XRPUSDT', '01:15', '03:00', 110),
        ]);
        $method = new \ReflectionMethod(TerminalBacktestService::class, 'simulate');
        $result = $method->invoke(app(TerminalBacktestService::class), $signals, 10000., 10.);
        $this->assertTrue($result['risk_halted']);
        $this->assertSame(2, $result['closed']);
        $this->assertSame(1, $result['skipped_risk']);
        $this->assertSame([2, 1], $result['trade_ids_desc']);
    }

    private function signal(int $id, string $symbol, string $open, string $close, float $exit): Signal
    {
        $signal = new Signal([
            'symbol' => $symbol, 'direction' => 'LONG',
            'entry_at' => '2026-01-01 '.$open, 'closed_at' => '2026-01-01 '.$close,
            'entry_price' => 100, 'stop_loss' => 99, 'close_price' => $exit,
        ]);
        $signal->id = $id;

        return $signal;
    }
}
