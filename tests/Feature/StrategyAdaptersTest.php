<?php

namespace Tests\Feature;

use App\Contracts\Trading\StrategyAdapter;
use App\DTOs\Signals\SignalListFilters;
use App\DTOs\Trading\InstrumentData;
use App\DTOs\Trading\StrategyResult;
use App\DTOs\Trading\StrategySignal;
use App\Models\Signal;
use App\Models\User;
use App\Repositories\Signals\SignalRepository;
use App\Services\Performance\TerminalBacktestService;
use App\Services\Signals\Import\StrategySignalImportService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyAdaptersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['terminal.instruments' => [['symbol' => 'BTCUSDT', 'label' => 'Bitcoin']]]);
        foreach (['test_win' => 110, 'test_loss' => 95] as $key => $exit) {
            config(["strategies.adapters.{$key}" => [
                'label' => $key, 'adapter' => FixtureAdapter::class, 'defaults' => ['exit' => $exit],
            ]]);
        }
    }

    public function test_two_adapters_persist_separate_identities_and_backtest_caches(): void
    {
        $importer = app(StrategySignalImportService::class);
        foreach (['test_win', 'test_loss'] as $key) {
            $result = $importer->import(
                exchange: 'binance', symbol: 'BTCUSDT', interval: '15m', candles: [], contextCandles: [],
                publish: true, publishAtEntry: true, simulateExits: true, strategyKey: $key,
            );
            $this->assertSame(1, $result['created']);
        }
        $this->assertSame(2, Signal::count());
        $this->assertArrayHasKey('execution', Signal::first()->strategy_params);
        $backtester = app(TerminalBacktestService::class);
        $win = $backtester->calculate('portfolio', null, null, 1, strategyKey: 'test_win');
        $loss = $backtester->calculate('portfolio', null, null, 1, strategyKey: 'test_loss');
        $this->assertSame(1, $win['closed']);
        $this->assertSame(1, $loss['closed']);
        $this->assertGreaterThan(0, $win['roi_pct']);
        $this->assertLessThan(0, $loss['roi_pct']);
        $this->assertSame('test_win', $win['strategy_key']);
        $history = $backtester->buildTradeHistory('portfolio', null, null, 1, strategyKey: 'test_loss');
        $this->assertSame('test_loss', Signal::find($history['trade_ids_desc'][0])->strategy_key);
        $page = app(SignalRepository::class)->paginatePublished(new SignalListFilters(strategyKey: 'test_win', status: 'all'));
        $this->assertSame(1, $page->total());
        $this->assertSame('test_win', $page->first()->strategy_key);
    }

    public function test_active_strategy_switch_and_dry_run_do_not_require_importer_changes(): void
    {
        config(['strategies.active' => 'test_win']);
        $result = app(StrategySignalImportService::class)->import(
            'binance', 'BTCUSDT', '15m', [], [], dryRun: true, simulateExits: true,
        );
        $this->assertSame(1, $result['total']);
        $this->assertSame(0, Signal::count());
        $this->assertSame('test_win', app(StrategyRegistry::class)->activeKey());
    }

    public function test_disabled_strategy_cannot_write_signals(): void
    {
        config(['strategies.adapters.test_win.enabled' => false]);
        $this->expectException(\InvalidArgumentException::class);
        app(StrategySignalImportService::class)->import('binance', 'BTCUSDT', '15m', [], [], strategyKey: 'test_win');
    }

    public function test_unknown_strategy_filter_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/performance/terminal?strategy=missing')->assertStatus(422);
        $this->actingAs($user)->get('/performance/terminal/history?strategy=missing')->assertStatus(422);
    }
}

/** Deterministic test adapter; not an offered trading algorithm. */
final class FixtureAdapter implements StrategyAdapter
{
    public function generate(InstrumentData $data, array $configuration, bool $simulateExits = false): StrategyResult
    {
        $entry = now()->startOfDay()->subDay()->valueOf();

        return new StrategyResult([new StrategySignal(
            symbol: $data->symbol, exchange: $data->exchange, direction: 'LONG', entryTimeMs: $entry,
            entryPrice: 100, stopLoss: 95, takeProfit: 110, rewardRisk: 2,
            exitTimeMs: $simulateExits ? $entry + 900_000 : null,
            exitPrice: $simulateExits ? (float) $configuration['exit'] : null,
            metadata: ['fixture' => true],
        )]);
    }
}
