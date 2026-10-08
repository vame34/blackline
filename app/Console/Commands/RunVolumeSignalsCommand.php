<?php

namespace App\Console\Commands;

use App\DTOs\Trading\Candle;
use App\Services\MarketData\MarketDataService;
use App\Services\Signals\Import\StrategySignalImportService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunVolumeSignalsCommand extends Command
{
    protected $aliases = ['signals:run-volume'];

    protected $signature = 'signals:run-strategy
        {--symbol= : Single symbol, e.g. BTCUSDT}
        {--symbols= : Comma-separated symbols list}
        {--interval=15m : Entry timeframe}
        {--context-interval=1h : Context timeframe}
        {--limit=1000 : Candles limit (max 1000)}
        {--context-limit=500 : Optional context candles limit}
        {--provider=binance : Market data provider}
        {--fallbacks= : Comma-separated fallback providers}
        {--strategy= : Registered strategy key (defaults to STRATEGY_ACTIVE)}
        {--exchange=binance : Exchange key}
        {--lookback-days= : Rolling historical window in days}
        {--full-range : Page candles over [start-time, end-time] instead of single limited fetch}
        {--category=volume-strategy : signal_categories.slug}
        {--created-by= : users.id (author)}
        {--dry-run : Do not write to DB}';

    protected $description = 'Run the selected strategy on closed candles and publish new signals.';

    public function handle(
        MarketDataService $marketData,
        StrategySignalImportService $service,
        TerminalInstrumentService $instruments,
    ): int {
        $symbols = $this->resolveSymbols($instruments);
        if ($symbols === []) {
            $this->error('No symbols to run.');

            return self::INVALID;
        }

        $interval = (string) $this->option('interval');
        $contextInterval = (string) $this->option('context-interval');
        $limit = (int) $this->option('limit');
        $contextLimit = $this->option('context-limit') !== null
            ? (int) $this->option('context-limit')
            : $limit;
        $provider = (string) $this->option('provider');
        $fallbacks = $this->parseFallbacks($this->option('fallbacks'));
        $exchange = (string) $this->option('exchange');
        $lookbackDaysOption = $this->option('lookback-days');
        $lookbackDays = is_numeric($lookbackDaysOption)
            ? max(1, (int) $lookbackDaysOption)
            : max(1, (int) config('signals.runtime.lookback_days', 30));
        $useFullRange = (bool) ($this->option('full-range') || config('signals.runtime.use_full_range', true));
        $endTimeMs = Carbon::now('UTC')->valueOf();
        $startTimeMs = Carbon::now('UTC')->subDays($lookbackDays)->valueOf();
        $entryIntervalMs = $this->intervalToMs($interval);
        if ($entryIntervalMs > 0) {
            $startTimeMs = $this->alignToInterval($startTimeMs, $entryIntervalMs);
            $endTimeMs = $this->alignToInterval($endTimeMs, $entryIntervalMs);
        }
        $contextPaddingDays = max(0, (int) (app(StrategyRegistry::class)->configuration($this->option('strategy') ?? app(StrategyRegistry::class)->activeKey())['contextPaddingDays'] ?? 15));
        $contextStartTimeMs = $useFullRange
            ? max(0, $startTimeMs - ($contextPaddingDays * 86_400_000))
            : null;
        $contextIntervalMs = $this->intervalToMs($contextInterval);
        if ($contextIntervalMs > 0 && $contextStartTimeMs !== null) {
            $contextStartTimeMs = $this->alignToInterval($contextStartTimeMs, $contextIntervalMs);
        }
        $category = (string) $this->option('category');
        $createdBy = $this->option('created-by') !== null ? (int) $this->option('created-by') : null;
        $dryRun = (bool) $this->option('dry-run');

        $totalCreated = 0;
        $totalSignals = 0;
        $totalClosed = 0;

        foreach ($symbols as $symbol) {
            $candles = $useFullRange
                ? $marketData->fetchKlinesRange(
                    symbol: $symbol,
                    interval: $interval,
                    startTimeMs: $startTimeMs,
                    endTimeMs: $endTimeMs,
                    provider: $provider,
                    fallbacks: $fallbacks,
                    limitPerRequest: $limit,
                )
                : $marketData->fetchKlines(
                    symbol: $symbol,
                    interval: $interval,
                    limit: $limit,
                    startTimeMs: null,
                    endTimeMs: null,
                    provider: $provider,
                    fallbacks: $fallbacks,
                );
            $candles = $this->filterClosedCandles($candles, $interval, $endTimeMs);

            if ($candles === []) {
                $this->warn("{$symbol}: no candles returned.");

                continue;
            }

            $contextCandles = $useFullRange
                ? $marketData->fetchKlinesRange(
                    symbol: $symbol,
                    interval: $contextInterval,
                    startTimeMs: $contextStartTimeMs,
                    endTimeMs: $endTimeMs,
                    provider: $provider,
                    fallbacks: $fallbacks,
                    limitPerRequest: $contextLimit,
                )
                : $marketData->fetchKlines(
                    symbol: $symbol,
                    interval: $contextInterval,
                    limit: $contextLimit,
                    startTimeMs: null,
                    endTimeMs: null,
                    provider: $provider,
                    fallbacks: $fallbacks,
                );
            $contextCandles = $this->filterClosedCandles($contextCandles, $contextInterval, $endTimeMs);

            $entryResult = $service->import(
                strategyKey: $this->option('strategy'),
                exchange: $exchange,
                symbol: strtoupper($symbol),
                interval: $interval,
                candles: $candles,
                contextCandles: $contextCandles,
                publish: true,
                categorySlug: $category,
                createdBy: $createdBy,
                dryRun: $dryRun,
                publishAtEntry: false,
                publishLatestOnly: true,
            );

            $closeResult = $service->import(
                strategyKey: $this->option('strategy'),
                exchange: $exchange,
                symbol: strtoupper($symbol),
                interval: $interval,
                candles: $candles,
                contextCandles: $contextCandles,
                publish: true,
                categorySlug: $category,
                createdBy: $createdBy,
                dryRun: $dryRun,
                publishAtEntry: false,
                publishLatestOnly: false,
                simulateExits: true,
                updateExistingOnly: true,
                closeOnTargetOnly: true,
            );

            $totalCreated += $entryResult['created'];
            $totalSignals += $entryResult['total'];
            $totalClosed += $closeResult['updated'] ?? 0;

            $this->info("{$symbol}: total {$entryResult['total']}, created {$entryResult['created']}, closed {$closeResult['updated']}");
        }

        $this->info("Run complete. Signals total: {$totalSignals}, created: {$totalCreated}, closed: {$totalClosed}");

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

    /**
     * @param  array<int, Candle>  $candles
     * @return array<int, Candle>
     */
    private function filterClosedCandles(array $candles, string $interval, int $nowMs): array
    {
        $intervalMs = $this->intervalToMs($interval);
        if ($intervalMs <= 0) {
            return $candles;
        }

        return array_values(array_filter(
            $candles,
            static fn (Candle $candle): bool => ($candle->openTimeMs + $intervalMs) <= $nowMs
        ));
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
