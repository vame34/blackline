<?php

namespace Tests\Unit;

use App\Enums\SignalDirection;
use App\Models\Signal;
use Tests\TestCase;

class SignalTest extends TestCase
{
    public function test_result_r_for_long_and_short(): void
    {
        $long = $this->makeSignal(100.0, 90.0, 120.0, SignalDirection::Long);
        $short = $this->makeSignal(100.0, 110.0, 80.0, SignalDirection::Short);

        $this->assertEqualsWithDelta(2.0, $long->resultR(), 0.0001);
        $this->assertEqualsWithDelta(2.0, $short->resultR(), 0.0001);
    }

    public function test_result_pct(): void
    {
        $long = $this->makeSignal(200.0, 180.0, 220.0, SignalDirection::Long);
        $short = $this->makeSignal(200.0, 210.0, 180.0, SignalDirection::Short);

        $this->assertEqualsWithDelta(0.10, $long->resultPct(), 0.0001);
        $this->assertEqualsWithDelta(0.10, $short->resultPct(), 0.0001);
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
