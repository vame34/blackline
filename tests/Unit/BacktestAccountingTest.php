<?php

namespace Tests\Unit;

use App\Models\Signal;
use App\Services\Performance\TerminalBacktestService;
use App\Services\Trading\Utils\Slippage;
use Tests\TestCase;

class BacktestAccountingTest extends TestCase
{
    public function test_only_an_adverse_open_is_a_stop_gap(): void
    {
        $this->assertSame(0.0, Slippage::adverseStopGapBps(101, 100, 'LONG'));
        $this->assertSame(0.0, Slippage::adverseStopGapBps(99, 100, 'SHORT'));
        $this->assertEquals(100, Slippage::adverseStopGapBps(99, 100, 'LONG'));
        $this->assertEquals(100, Slippage::adverseStopGapBps(101, 100, 'SHORT'));
    }

    public function test_entry_fee_is_included_in_classification_and_true_r(): void
    {
        config(['trading.commissionRate' => .001]);
        $signal = new Signal([
            'symbol' => 'BTCUSDT', 'direction' => 'LONG',
            'entry_at' => '2026-01-01 00:00:00', 'closed_at' => '2026-01-01 01:00:00',
            'entry_price' => 100, 'stop_loss' => 99, 'close_price' => 100.15,
        ]);
        $signal->id = 1;
        $method = new \ReflectionMethod(TerminalBacktestService::class, 'simulate');
        $service = app(TerminalBacktestService::class);
        $result = $method->invoke($service, collect([$signal]), 10000., 10.);
        $this->assertSame(0, $result['wins']);
        $this->assertSame(1, $result['losses']);
        $this->assertEqualsWithDelta(-.05015, $result['avg_r'], .000001);
        $this->assertEqualsWithDelta($result['gross_pnl'] - $result['total_entry_fees'] - $result['total_exit_fees'], $result['net_pnl_after_fees'], .02);
        $other = $method->invoke($service, collect([$signal]), 10000., 2.);
        $this->assertEquals($result['commission_rate_effective'], $other['commission_rate_effective']);
        $this->assertEquals($result['avg_r'], $other['avg_r']);
    }

    public function test_worst_configured_stop_fill_and_fees_fit_the_planned_budget(): void
    {
        config([
            'trading.commissionRate' => .0004,
            'trading.dynamicStopSlippageEnabled' => true,
            'trading.dynamicStopSlippageCapBps' => 120,
        ]);
        foreach (['LONG', 'SHORT'] as $direction) {
            $stop = $direction === 'LONG' ? 99 : 101;
            $signal = new Signal([
                'symbol' => 'BTCUSDT', 'direction' => $direction,
                'entry_at' => '2026-01-01 00:00:00', 'closed_at' => '2026-01-01 01:00:00',
                'entry_price' => 100, 'stop_loss' => $stop,
                'close_price' => Slippage::applyStopSlippage($stop, $direction, 120),
            ]);
            $signal->id = 1;
            $method = new \ReflectionMethod(TerminalBacktestService::class, 'simulate');
            $result = $method->invoke(app(TerminalBacktestService::class), collect([$signal]), 10000., 10.);
            $this->assertGreaterThanOrEqual(-50, $result['net_pnl_after_fees']);
            $this->assertSame(1, $result['closed']);
        }
    }
}
