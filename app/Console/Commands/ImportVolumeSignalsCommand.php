<?php

namespace App\Console\Commands;

use App\DTOs\Trading\Candle;
use App\Services\Signals\Import\StrategySignalImportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportVolumeSignalsCommand extends Command
{
    protected $signature = 'signals:import-volume
        {candles : Path to JSON array of candles}
        {--context= : Optional path to JSON array of context candles}
        {--strategy= : Registered strategy key (defaults to STRATEGY_ACTIVE)}
        {--exchange=bybit : Exchange key}
        {--symbol= : Symbol, e.g. BTCUSDT}
        {--interval=1h : Timeframe, e.g. 5m/1h}
        {--category=volume-strategy : signal_categories.slug}
        {--created-by= : users.id (author)}
        {--publish : Create as published}
        {--dry-run : Do not write to DB}';

    protected $description = 'Import VolumeStrategy entry signals (Entry/SL/TP) from candle JSON.';

    public function handle(StrategySignalImportService $service): int
    {
        $candlesPath = (string) $this->argument('candles');
        $contextPath = (string) ($this->option('context') ?? '');
        $exchange = (string) $this->option('exchange');
        $symbol = (string) ($this->option('symbol') ?? '');
        $interval = (string) $this->option('interval');
        $category = (string) $this->option('category');
        $createdBy = $this->option('created-by') !== null ? (int) $this->option('created-by') : null;
        $publish = (bool) $this->option('publish');
        $dryRun = (bool) $this->option('dry-run');

        if ($symbol === '') {
            $this->error('--symbol is required.');

            return self::INVALID;
        }

        $candlesPayload = $this->readJsonArray($candlesPath);
        if ($candlesPayload === null) {
            $this->error("Invalid candles JSON: {$candlesPath}");

            return self::FAILURE;
        }

        $contextPayload = $contextPath !== '' ? $this->readJsonArray($contextPath) : $candlesPayload;
        if ($contextPayload === null) {
            $this->error("Invalid context JSON: {$contextPath}");

            return self::FAILURE;
        }

        $candles = array_map(fn (array $item) => Candle::fromArray($item), $candlesPayload);
        $contextCandles = array_map(fn (array $item) => Candle::fromArray($item), $contextPayload);

        $result = $service->import(
            strategyKey: $this->option('strategy'),
            exchange: $exchange,
            symbol: $symbol,
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
     * @return array<int, array>|null
     */
    private function readJsonArray(string $path): ?array
    {
        if ($path === '' || ! File::exists($path)) {
            return null;
        }
        $raw = File::get($path);
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return null;
        }

        $items = [];
        foreach ($decoded as $item) {
            if (! is_array($item)) {
                return null;
            }
            $items[] = $item;
        }

        return $items;
    }
}
