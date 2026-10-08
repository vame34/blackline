<?php

namespace App\Console\Commands;

use App\Models\Signal;
use App\Repositories\Signals\SignalBackfillBatchRepository;
use App\Services\MarketData\MarketDataService;
use App\Services\Signals\Import\StrategySignalImportService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Console\Command;

class BackfillVolumeSignalsCommand extends Command
{
    protected $signature = 'signals:backfill-volume
        {--symbol= : Single symbol, e.g. BTCUSDT}
        {--symbols= : Comma-separated symbols list}
        {--interval=15m : Entry timeframe}
        {--context-interval=1h : Context timeframe}
        {--limit=1000 : Candles limit (max 1000)}
        {--context-limit= : Optional context candles limit}
        {--start-time= : Optional start time (ms)}
        {--end-time= : Optional end time (ms)}
        {--signal-start-time= : Optional signal entry lower bound (ms, inclusive)}
        {--signal-end-time= : Optional signal entry upper bound (ms, exclusive)}
        {--provider=binance : Market data provider}
        {--fallbacks= : Comma-separated fallback providers}
        {--strategy= : Registered strategy key (defaults to STRATEGY_ACTIVE)}
        {--exchange=binance : Exchange key}
        {--category=volume-strategy : signal_categories.slug}
        {--created-by= : users.id (author)}
        {--draft : Create as draft}
        {--entry-only : Deprecated alias (backfill already imports entry signals by default)}
        {--simulate-exits : Simulate exits (full backtest mode)}
        {--dry-run : Do not write to DB}
        {--broadcast : Broadcast published events}
        {--full-range : Page candles over [start-time, end-time] instead of single limited fetch}
        {--no-batch : Do not store backfill batch metadata}';

    protected $description = 'Backfill VolumeStrategy signals for terminal instruments.';

    public function handle(
        MarketDataService $marketData,
        StrategySignalImportService $service,
        TerminalInstrumentService $instruments,
        SignalBackfillBatchRepository $batches,
    ): int {
        $symbols = $this->resolveSymbols($instruments);
        if ($symbols === []) {
            $this->error('No symbols to backfill.');

            return self::INVALID;
        }

        $interval = (string) $this->option('interval');
        $contextInterval = (string) $this->option('context-interval');
        $limit = (int) $this->option('limit');
        $contextLimit = $this->option('context-limit') !== null
            ? (int) $this->option('context-limit')
            : $limit;
        $startTime = $this->option('start-time') !== null ? (int) $this->option('start-time') : null;
        $endTime = $this->option('end-time') !== null ? (int) $this->option('end-time') : null;
        $entryIntervalMs = $this->intervalToMs($interval);
        if ($entryIntervalMs > 0) {
            if ($startTime !== null) {
                $startTime = $this->alignToInterval($startTime, $entryIntervalMs);
            }
            if ($endTime !== null) {
                $endTime = $this->alignToInterval($endTime, $entryIntervalMs);
            }
        }
        $contextPaddingDays = max(0, (int) (app(StrategyRegistry::class)->configuration($this->option('strategy') ?? app(StrategyRegistry::class)->activeKey())['contextPaddingDays'] ?? 15));
        $contextStartTime = $startTime !== null
            ? max(0, $startTime - ($contextPaddingDays * 86_400_000))
            : null;
        $contextIntervalMs = $this->intervalToMs($contextInterval);
        if ($contextIntervalMs > 0 && $contextStartTime !== null) {
            $contextStartTime = $this->alignToInterval($contextStartTime, $contextIntervalMs);
        }
        $provider = (string) $this->option('provider');
        $fallbacks = $this->parseFallbacks($this->option('fallbacks'));
        $exchange = (string) $this->option('exchange');
        $signalStartTime = $this->option('signal-start-time') !== null ? (int) $this->option('signal-start-time') : null;
        $signalEndTime = $this->option('signal-end-time') !== null ? (int) $this->option('signal-end-time') : null;
        $fullRange = (bool) $this->option('full-range');
        $category = (string) $this->option('category');
        $createdBy = $this->option('created-by') !== null ? (int) $this->option('created-by') : null;
        $publish = ! $this->option('draft');
        $dryRun = (bool) $this->option('dry-run');
        $broadcast = (bool) $this->option('broadcast');
        $simulateExits = (bool) $this->option('simulate-exits');

        $totalCreated = 0;
        $storeBatch = ! $this->option('no-batch');
        $batch = null;
        $batchResults = [];

        if ($storeBatch) {
            $batch = $batches->create([
                'exchange' => $exchange,
                'provider' => $provider,
                'interval' => $interval,
                'context_interval' => $contextInterval,
                'limit' => $limit,
                'context_limit' => $contextLimit,
                'start_time_ms' => $startTime,
                'end_time_ms' => $endTime,
                'symbols' => $symbols,
                'fallbacks' => $fallbacks,
                'category_slug' => $category,
                'created_by' => $createdBy,
                'publish' => $publish,
                'simulate_exits' => $simulateExits,
                'strategy_key' => $this->option('strategy') ?? app(StrategyRegistry::class)->activeKey(),
                'dry_run' => $dryRun,
                'broadcast' => $broadcast,
                'started_at' => now(),
            ]);
        }

        $totalSignals = 0;

        foreach ($symbols as $symbol) {
            $candles = $fullRange
                ? $marketData->fetchKlinesRange(
                    symbol: $symbol,
                    interval: $interval,
                    startTimeMs: $startTime,
                    endTimeMs: $endTime,
                    provider: $provider,
                    fallbacks: $fallbacks,
                    limitPerRequest: $limit,
                )
                : $marketData->fetchKlines(
                    symbol: $symbol,
                    interval: $interval,
                    limit: $limit,
                    startTimeMs: $startTime,
                    endTimeMs: $endTime,
                    provider: $provider,
                    fallbacks: $fallbacks,
                );

            if ($candles === []) {
                $this->warn("{$symbol}: no candles returned.");
                if ($batch) {
                    $batchResults[$symbol] = [
                        'status' => 'no_candles',
                        'total' => 0,
                        'created' => 0,
                        'candles' => 0,
                        'context_candles' => 0,
                    ];
                }

                continue;
            }

            $contextCandles = $fullRange
                ? $marketData->fetchKlinesRange(
                    symbol: $symbol,
                    interval: $contextInterval,
                    startTimeMs: $contextStartTime,
                    endTimeMs: $endTime,
                    provider: $provider,
                    fallbacks: $fallbacks,
                    limitPerRequest: $contextLimit,
                )
                : $marketData->fetchKlines(
                    symbol: $symbol,
                    interval: $contextInterval,
                    limit: $contextLimit,
                    startTimeMs: $contextStartTime,
                    endTimeMs: $endTime,
                    provider: $provider,
                    fallbacks: $fallbacks,
                );

            $run = function () use (
                $service,
                $exchange,
                $symbol,
                $interval,
                $candles,
                $contextCandles,
                $publish,
                $category,
                $createdBy,
                $dryRun,
                $simulateExits,
                $signalStartTime,
                $signalEndTime,
            ): array {
                return $service->import(
                    strategyKey: $this->option('strategy'),
                    exchange: $exchange,
                    symbol: strtoupper($symbol),
                    interval: $interval,
                    candles: $candles,
                    contextCandles: $contextCandles,
                    publish: $publish,
                    categorySlug: $category,
                    createdBy: $createdBy,
                    dryRun: $dryRun,
                    publishAtEntry: $publish,
                    simulateExits: $simulateExits,
                    signalStartTimeMs: $signalStartTime,
                    signalEndTimeMs: $signalEndTime,
                );
            };

            $result = $broadcast ? $run() : Signal::withoutEvents($run);

            $totalCreated += $result['created'];
            $totalSignals += $result['total'];

            $this->info("{$symbol}: total {$result['total']}, created {$result['created']}");
            if ($batch) {
                $batchResults[$symbol] = [
                    'total' => $result['total'],
                    'created' => $result['created'],
                    'candles' => count($candles),
                    'context_candles' => count($contextCandles),
                ];
            }

        }

        if ($batch) {
            $batches->update($batch, [
                'total_signals' => $totalSignals,
                'total_created' => $totalCreated,
                'results' => $batchResults,
                'finished_at' => now(),
            ]);
        }

        $this->info("Backfill complete. Signals total: {$totalSignals}, created: {$totalCreated}");

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSymbols(TerminalInstrumentService $instruments): array
    {
        $single = $this->option('symbol');
        if (is_string($single) && trim($single) !== '') {
            return [strtoupper(trim($single))];
        }

        $list = $this->option('symbols');
        if (is_string($list) && trim($list) !== '') {
            $items = array_map('trim', explode(',', $list));
            $items = array_values(array_filter($items, static fn (string $item) => $item !== ''));

            return array_map('strtoupper', $items);
        }

        return $instruments->symbols();
    }

    /**
     * @return array<int, string>|null
     */
    private function parseFallbacks(mixed $value): ?array
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $items = array_map('trim', explode(',', $value));
        $items = array_values(array_filter($items, static fn (string $item) => $item !== ''));

        return $items !== [] ? $items : null;
    }

    private function intervalToMs(string $interval): int
    {
        return match (strtolower($interval)) {
            '1m' => 60_000,
            '3m' => 180_000,
            '5m' => 300_000,
            '15m' => 900_000,
            '30m' => 1_800_000,
            '1h' => 3_600_000,
            '2h' => 7_200_000,
            '4h' => 14_400_000,
            '6h' => 21_600_000,
            '12h' => 43_200_000,
            '1d' => 86_400_000,
            '1w' => 604_800_000,
            default => 0,
        };
    }

    private function alignToInterval(int $timeMs, int $intervalMs): int
    {
        if ($intervalMs <= 0) {
            return $timeMs;
        }

        return intdiv($timeMs, $intervalMs) * $intervalMs;
    }
}
