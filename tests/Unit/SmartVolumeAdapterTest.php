<?php

namespace Tests\Unit;

use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\StrategySignal;
use App\Services\Trading\Adapters\SmartVolumeAdapter;
use Tests\TestCase;

class SmartVolumeAdapterTest extends TestCase
{
    public function test_empty_market_data_does_not_create_fake_signals(): void
    {
        $result = (new SmartVolumeAdapter)->generate(new InstrumentData('BTCUSDT', 'binance', '15m', [], []), []);
        $this->assertSame([], $result->signals);
    }

    public function test_full_future_window_volume_calibration_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new SmartVolumeAdapter)->generate(new InstrumentData('BTCUSDT', 'binance', '15m', [], []), ['deriveContextVolumeMultiplier' => true]);
    }

    public function test_invalid_adapter_exit_cannot_be_imported(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new StrategySignal('BTCUSDT', 'binance', 'LONG', 1000, 100, 95, 110, 2, 999, 110);
    }
}
