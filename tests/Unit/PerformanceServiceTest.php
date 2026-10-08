<?php

namespace Tests\Unit;

use App\Enums\SignalDirection;
use App\Models\Signal;
use App\Services\Performance\PerformanceService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PerformanceServiceTest extends TestCase
{
    public function test_calculate_from_signals_returns_metrics(): void
    {
        $signals = new Collection([
            $this->makeSignal(100.0, 90.0, 120.0, SignalDirection::Long),
            $this->makeSignal(100.0, 110.0, 110.0, SignalDirection::Short),
            $this->makeSignal(100.0, 90.0, 110.0, SignalDirection::Long),
        ]);

        $service = new PerformanceService();
        $metrics = $service->calculateFromSignals($signals);

        $this->assertSame(3, $metrics['total_trades']);
        $this->assertSame(2, $metrics['wins']);
        $this->assertSame(1, $metrics['losses']);
        $this->assertEqualsWithDelta(2 / 3, $metrics['win_rate'], 0.0001);
        $this->assertEqualsWithDelta(2 / 3, $metrics['avg_r'], 0.0001);
        $this->assertEqualsWithDelta(3.0, $metrics['profit_factor'], 0.0001);
        $this->assertEqualsWithDelta(1 / 3, $metrics['max_drawdown'], 0.0001);
        $this->assertSame([3.0, 2.0, 3.0], $metrics['equity_curve']);
    }

    public function test_calculate_from_signals_returns_empty_metrics(): void
    {
        $service = new PerformanceService();
        $metrics = $service->calculateFromSignals(new Collection());

        $this->assertSame(0, $metrics['total_trades']);
        $this->assertSame(0, $metrics['wins']);
        $this->assertSame(0, $metrics['losses']);
        $this->assertNull($metrics['win_rate']);
        $this->assertNull($metrics['avg_r']);
        $this->assertNull($metrics['profit_factor']);
        $this->assertNull($metrics['max_drawdown']);
        $this->assertSame([], $metrics['equity_curve']);
    }

    private function makeSignal(
        float $entry,
        float $stop,
        float $close,
        SignalDirection $direction,
    ): Signal {
        $signal = new Signal();
        $signal->entry_price = $entry;
        $signal->stop_loss = $stop;
        $signal->close_price = $close;
        $signal->direction = $direction;

        return $signal;
    }
}
