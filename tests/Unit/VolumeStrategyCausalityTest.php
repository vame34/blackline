<?php

namespace Tests\Unit;

use App\DTOs\Trading\Candle;
use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\SpikeArea;
use App\Services\Trading\Strategies\VolumeStrategy;
use App\Services\Trading\Strategies\VolumeStrategyConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class VolumeStrategyCausalityTest extends TestCase
{
    private const START = 1735689600000;

    private const QUARTER = 900000;

    private const HOUR = 3600000;

    public static function directions(): array
    {
        return [['LONG'], ['SHORT']];
    }

    #[DataProvider('directions')]
    public function test_future_volume_levels_do_not_change_existing_entries(string $direction): void
    {
        [$candles, $context] = $this->fixture($direction);
        $prefix = $this->entries($candles, $context);
        self::assertNotEmpty($prefix);
        $future = $this->candle(8, 13, 13, 11, 11, 10000, $direction);
        $extended = $this->entries([...$candles, $future], $context);
        $existing = array_values(array_filter($extended, fn ($s) => $s->entryTimeMs <= self::START + 8 * self::QUARTER));
        self::assertEquals($prefix, $existing);
        foreach ($prefix as $signal) {
            self::assertSame($direction, $signal->direction);
            self::assertEquals($direction === 'LONG' ? 13 : 9, $signal->takeProfit);
        }
    }

    #[DataProvider('directions')]
    public function test_unclosed_hour_cannot_change_earlier_entries(string $direction): void
    {
        [$candles, $context] = $this->fixture($direction);
        $before = $this->entries($candles, $context);
        $context[4] = new Candle(self::START + self::HOUR, 1, 1, 1, 1, 100);
        $after = $this->entries($candles, $context);
        $earlier = fn ($signals) => array_values(array_filter($signals, fn ($s) => $s->entryTimeMs < self::START + 2 * self::HOUR));
        self::assertNotEmpty($earlier($before));
        self::assertEquals($earlier($before), $earlier($after));
    }

    #[DataProvider('directions')]
    public function test_unclosed_hour_cannot_change_earlier_backtest_trades(string $direction): void
    {
        [$candles, $context] = $this->fixture($direction);
        $candles[] = $this->candle(8, 11, 20, 10, 20, 100, $direction);
        $run = fn ($hours) => $this->strategy()->generateSignals(new InstrumentData('BTCUSDT', 'binance', '15m', $candles, $hours))['signals'];
        $before = $run($context);
        $context[4] = new Candle(self::START + self::HOUR, 1, 1, 1, 1, 100);
        $after = $run($context);
        $earlier = fn ($signals) => array_values(array_filter($signals, fn ($s) => $s->entryTimeMs < self::START + 2 * self::HOUR));
        self::assertNotEmpty($earlier($before));
        self::assertEquals($earlier($before), $earlier($after));
    }

    #[DataProvider('directions')]
    public function test_target_levels_expire_with_the_entry_level_window(string $direction): void
    {
        $method = new \ReflectionMethod(VolumeStrategy::class, 'findTargetLevel');
        $strategy = new VolumeStrategy(new VolumeStrategyConfig(lookback: 10));
        $kind = $direction === 'LONG' ? 'bearish' : 'bullish';
        $sign = $direction === 'LONG' ? 1 : -1;
        $expired = new SpikeArea(9, 100 + $sign, $kind, 100, 0);
        $boundary = new SpikeArea(10, 100 + 3 * $sign, $kind, 100, 0);
        $future = new SpikeArea(21, 100 + 2 * $sign, $kind, 100, 0);
        self::assertEquals(100 + 3 * $sign, $method->invoke($strategy, [$expired, $boundary, $future], 20, $direction, 100));
        self::assertNull($method->invoke($strategy, [$expired, $future], 20, $direction, 100));
    }

    public function test_context_cannot_be_used_before_its_first_close(): void
    {
        [$candles, $context] = $this->fixture('LONG');
        $signals = $this->entries(array_slice($candles, 0, 3), array_slice($context, 3));
        self::assertSame([], $signals);
    }

    public function test_closed_hour_is_available_at_the_exact_boundary(): void
    {
        [$candles, $context] = $this->fixture('LONG');
        $withClosedHour = $this->entries(array_slice($candles, 0, 4), $context);
        self::assertNotEmpty($withClosedHour);
        self::assertEquals(self::START + self::HOUR, $withClosedHour[0]->entryTimeMs);
        $context[3] = new Candle(self::START, 1, 1, 1, 1, 100);
        self::assertSame([], $this->entries(array_slice($candles, 0, 4), $context));
    }

    #[DataProvider('directions')]
    public function test_backtest_uses_the_same_entry_levels_as_signal_generation(string $direction): void
    {
        [$candles, $context] = $this->fixture($direction);
        $candles[] = $this->candle(8, 11, 20, 10, 20, 100, $direction);
        $entries = $this->entries($candles, $context);
        $trades = $this->strategy()->generateSignals(new InstrumentData('BTCUSDT', 'binance', '15m', $candles, $context))['signals'];
        self::assertNotEmpty($trades);
        foreach ($trades as $trade) {
            $matches = array_values(array_filter($entries, fn ($s) => $s->entryTimeMs === $trade->entryTimeMs));
            self::assertNotEmpty($matches);
            self::assertEquals($matches[0]->takeProfit, $trade->takeProfit);
            self::assertEquals($matches[0]->stopLoss, $trade->stopLoss);
        }
    }

    #[DataProvider('directions')]
    public function test_stop_on_first_bar_after_entry_is_recorded_after_entry(string $direction): void
    {
        [$candles, $context] = $this->fixture($direction);
        $candles[4] = $this->candle(4, 11, 11.2, 8, 10, 100, $direction);
        $trades = $this->strategy()->generateSignals(new InstrumentData('BTCUSDT', 'binance', '15m', $candles, $context))['signals'];
        self::assertNotEmpty($trades);
        self::assertEquals(self::START + self::HOUR, $trades[0]->entryTimeMs);
        self::assertEquals(self::START + self::HOUR + self::QUARTER, $trades[0]->exitTimeMs);
        self::assertEquals($trades[0]->stopLoss, $trades[0]->exitPrice);
    }

    private function strategy(): VolumeStrategy
    {
        return new VolumeStrategy(new VolumeStrategyConfig(
            contextFastPeriod: 1, contextSlowPeriod: 2,
            entryFastPeriod: 1, entryMidPeriod: 1, entrySlowPeriod: 1,
            atrPeriod: 1, stopAtrMultiplier: 0,
            minStopPercent: 0, maxStopPercent: 0, minStopTicks: 0,
            minRewardRatio: 0, volumeWindow: 2, spikeMultiplier: 1.5,
            rsiPeriod: 2, contextRsiPeriod: 2, widgetFilter: false,
        ));
    }

    private function entries(array $candles, array $context): array
    {
        return $this->strategy()->generateEntrySignals(new InstrumentData('BTCUSDT', 'binance', '15m', $candles, $context))['signals'];
    }

    private function fixture(string $direction): array
    {
        $candles = [
            $this->candle(0, 10, 11, 9.8, 10.5, 10, $direction),
            $this->candle(1, 14, 14, 12, 12, 100, $direction),
            $this->candle(2, 10, 11.2, 9.8, 11, 500, $direction),
        ];
        for ($i = 3; $i < 8; $i++) {
            $candles[] = $this->candle($i, 10.2, 11.2, 10, 11, 300, $direction);
        }
        $context = [];
        for ($i = -3; $i <= 2; $i++) {
            $price = $direction === 'LONG' ? 13 + $i : 22 - (13 + $i);
            $context[] = new Candle(self::START + $i * self::HOUR, $price, $price, $price, $price, 100);
        }

        return [$candles, $context];
    }

    private function candle(int $index, float $open, float $high, float $low, float $close, float $volume, string $direction): Candle
    {
        if ($direction === 'SHORT') {
            [$open, $high, $low, $close] = [22 - $open, 22 - $low, 22 - $high, 22 - $close];
        }

        return new Candle(self::START + $index * self::QUARTER, $open, $high, $low, $close, $volume);
    }
}
