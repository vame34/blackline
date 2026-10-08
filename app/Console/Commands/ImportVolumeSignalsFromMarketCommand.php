<?php

namespace App\Console\Commands;

use App\Services\MarketData\MarketDataService;
use App\Services\Signals\Import\StrategySignalImportService;
use Illuminate\Console\Command;

class ImportVolumeSignalsFromMarketCommand extends Command
{
    protected $signature = 'signals:import-volume-market
        {--symbol= : Symbol, e.g. BTCUSDT}
        {--interval=1h : Timeframe, e.g. 5m/1h}
        {--limit=500 : Candles limit (max 1000)}
        {--context-limit= : Optional context candles limit}
        {--start-time= : Optional start time (ms)}
        {--end-time= : Optional end time (ms)}
        {--provider= : Market data provider (binance/bybit)}
        {--fallbacks= : Comma-separated fallback providers}
        {--strategy= : Registered strategy key (defaults to STRATEGY_ACTIVE)}
        {--exchange=bybit : Exchange key}
        {--category=volume-strategy : signal_categories.slug}
        {--created-by= : users.id (author)}
        {--publish : Create as published}
        {--dry-run : Do not write to DB}';

    protected $description = 'Import VolumeStrategy entry signals from market data providers.';

    public function handle(
        MarketDataService $marketData,
        StrategySignalImportService $service,
    ): int {
        $symbol = (string) ($this->option('symbol') ?? '');
        $interval = (string) $this->option('interval');
        $limit = (int) $this->option('limit');
        $contextLimit = $this->option('context-limit') !== null
            ? (int) $this->option('context-limit')
            : $limit;
        $startTime = $this->option('start-time') !== null ? (int) $this->option('start-time') : null;
        $endTime = $this->option('end-time') !== null ? (int) $this->option('end-time') : null;
        $provider = $this->option('provider') !== null ? (string) $this->option('provider') : null;
        $fallbacks = $this->parseFallbacks($this->option('fallbacks'));
        $exchange = (string) $this->option('exchange');
        $category = (string) $this->option('category');
        $createdBy = $this->option('created-by') !== null ? (int) $this->option('created-by') : null;
        $publish = (bool) $this->option('publish');
        $dryRun = (bool) $this->option('dry-run');

        if ($symbol === '') {
            $this->error('--symbol is required.');

            return self::INVALID;
        }

        $candles = $marketData->fetchKlines(
            symbol: $symbol,
            interval: $interval,
            limit: $limit,
            startTimeMs: $startTime,
            endTimeMs: $endTime,
            provider: $provider,
            fallbacks: $fallbacks,
        );

        if ($candles === []) {
            $this->error('No candles returned from providers.');

            return self::FAILURE;
        }

        $contextCandles = $candles;
        if ($contextLimit !== $limit) {
            $contextCandles = $marketData->fetchKlines(
                symbol: $symbol,
                interval: $interval,
                limit: $contextLimit,
                startTimeMs: $startTime,
                endTimeMs: $endTime,
                provider: $provider,
                fallbacks: $fallbacks,
            );
        }

        $result = $service->import(
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
        );

        $this->info("Signals total: {$result['total']}, created: {$result['created']}");

        return self::SUCCESS;
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
        $items = array_values(array_filter($items, fn (string $item) => $item !== ''));

        return $items !== [] ? $items : null;
    }
}
