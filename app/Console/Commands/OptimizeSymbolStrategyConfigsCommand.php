<?php

namespace App\Console\Commands;

use App\Services\MarketData\MarketDataService;
use App\Services\Signals\Import\StrategySignalImportService;
use App\Services\Terminal\TerminalInstrumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class OptimizeSymbolStrategyConfigsCommand extends Command
{
    protected $signature = 'signals:optimize-symbol-configs
        {--symbols= : Comma-separated symbols list}
        {--interval=15m : Entry timeframe}
        {--context-interval=1h : Context timeframe}
        {--days=365 : Lookback period in days}
        {--provider=binance : Market data provider}
        {--fallbacks=bybit : Comma-separated fallback providers}
        {--limit=1000 : Candles limit per API request}
        {--stop-grid=0.6,0.8,1.0 : stopAtrMultiplier candidates}
        {--reward-grid=2.0,3.0,4.0 : rewardMultiplier candidates}
        {--widget-grid=false,true : widgetFilter candidates}
        {--output= : Optional JSON output path}';

    protected $description = 'Find per-symbol strategy params with max sum% over the period.';

    public function handle(
        MarketDataService $marketData,
        StrategySignalImportService $import,
        TerminalInstrumentService $instruments,
    ): int {
        $symbols = $this->resolveSymbols($instruments);
        if ($symbols === []) {
            $this->error('No symbols to optimize.');

            return self::INVALID;
        }

        $interval = (string) $this->option('interval');
        $contextInterval = (string) $this->option('context-interval');
        $provider = (string) $this->option('provider');
        $fallbacks = $this->parseFallbacks($this->option('fallbacks'));
        $limit = max(1, (int) $this->option('limit'));
        $days = max(1, (int) $this->option('days'));
        $output = $this->option('output');

        $stops = $this->parseFloatGrid((string) $this->option('stop-grid'));
        $rewards = $this->parseFloatGrid((string) $this->option('reward-grid'));
        $widgets = $this->parseBoolGrid((string) $this->option('widget-grid'));

        if ($stops === [] || $rewards === [] || $widgets === []) {
            $this->error('Invalid parameter grids.');

            return self::INVALID;
        }

        $entryIntervalMs = $this->intervalToMs($interval);
        $contextIntervalMs = $this->intervalToMs($contextInterval);
        $endTimeMs = Carbon::now('UTC')->valueOf();
        $startTimeMs = Carbon::now('UTC')->subDays($days)->valueOf();
        if ($entryIntervalMs > 0) {
            $startTimeMs = $this->alignToInterval($startTimeMs, $entryIntervalMs);
            $endTimeMs = $this->alignToInterval($endTimeMs, $entryIntervalMs);
        }
        $contextPaddingDays = max(0, (int) config('signals.volume_strategy.contextPaddingDays', 15));
        $contextStartTimeMs = max(0, $startTimeMs - ($contextPaddingDays * 86_400_000));
        if ($contextIntervalMs > 0) {
            $contextStartTimeMs = $this->alignToInterval($contextStartTimeMs, $contextIntervalMs);
        }

        $rows = [];
        $overrides = [];

        foreach ($symbols as $symbol) {
            $this->line("Optimizing {$symbol}...");

            $candles = $marketData->fetchKlinesRange(
                symbol: $symbol,
                interval: $interval,
                startTimeMs: $startTimeMs,
                endTimeMs: $endTimeMs,
                provider: $provider,
                fallbacks: $fallbacks,
                limitPerRequest: $limit,
            );
            $contextCandles = $marketData->fetchKlinesRange(
                symbol: $symbol,
                interval: $contextInterval,
                startTimeMs: $contextStartTimeMs,
                endTimeMs: $endTimeMs,
                provider: $provider,
                fallbacks: $fallbacks,
                limitPerRequest: $limit,
            );

            if ($candles === [] || $contextCandles === []) {
                $this->warn("{$symbol}: no candles, skipped.");

                continue;
            }

            $best = null;
            foreach ($stops as $stop) {
                foreach ($rewards as $reward) {
                    foreach ($widgets as $widget) {
                        $result = $import->import(
                            exchange: (string) config('terminal.exchange', 'binance'),
                            symbol: $symbol,
                            interval: $interval,
                            candles: $candles,
                            contextCandles: $contextCandles,
                            publish: true,
                            categorySlug: 'volume-strategy',
                            createdBy: null,
                            dryRun: true,
                            publishAtEntry: true,
                            simulateExits: true,
                            strategyKey: 'smart_volume_v1',
                            signalStartTimeMs: $startTimeMs,
                            signalEndTimeMs: $endTimeMs,
                            configOverrides: [
                                'stopAtrMultiplier' => $stop,
                                'rewardMultiplier' => $reward,
                                'widgetFilter' => $widget,
                            ],
                            collectStats: true,
                        );

                        $candidate = [
                            'stopAtrMultiplier' => $stop,
                            'rewardMultiplier' => $reward,
                            'widgetFilter' => $widget,
                            'signals' => (int) ($result['total'] ?? 0),
                            'wins' => (int) ($result['wins'] ?? 0),
                            'losses' => (int) ($result['losses'] ?? 0),
                            'winrate' => round((float) ($result['winrate'] ?? 0.0), 4),
                            'sum_pct' => round((float) ($result['sum_pct'] ?? 0.0), 4),
                        ];

                        if ($best === null || $this->isBetter($candidate, $best)) {
                            $best = $candidate;
                        }
                    }
                }
            }

            if ($best === null) {
                continue;
            }

            $rows[] = array_merge(['symbol' => $symbol], $best);
            $overrides[$symbol] = [
                'stopAtrMultiplier' => $best['stopAtrMultiplier'],
                'rewardMultiplier' => $best['rewardMultiplier'],
                'widgetFilter' => $best['widgetFilter'],
            ];

            $this->info(sprintf(
                '%s => stop=%s reward=%s widget=%s | sum%%=%s | signals=%d | winrate=%s%%',
                $symbol,
                (string) $best['stopAtrMultiplier'],
                (string) $best['rewardMultiplier'],
                $best['widgetFilter'] ? 'true' : 'false',
                number_format((float) $best['sum_pct'], 4, '.', ''),
                (int) $best['signals'],
                number_format((float) $best['winrate'], 4, '.', ''),
            ));
        }

        $payload = [
            'generated_at_utc' => Carbon::now('UTC')->toIso8601String(),
            'interval' => $interval,
            'context_interval' => $contextInterval,
            'period_days' => $days,
            'provider' => $provider,
            'fallbacks' => $fallbacks,
            'grid' => [
                'stop' => $stops,
                'reward' => $rewards,
                'widget' => $widgets,
            ],
            'results' => $rows,
            'symbol_overrides' => $overrides,
            'env_value' => json_encode($overrides, JSON_UNESCAPED_SLASHES),
        ];

        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            $this->error('Failed to encode report JSON.');

            return self::FAILURE;
        }

        if (is_string($output) && trim($output) !== '') {
            $path = str_starts_with($output, '/')
                ? $output
                : base_path($output);
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $json.PHP_EOL);
            $this->info("Report saved: {$path}");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function resolveSymbols(TerminalInstrumentService $instruments): array
    {
        $list = $this->option('symbols');
        if (is_string($list) && trim($list) !== '') {
            $items = array_map('trim', explode(',', $list));
            $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

            return array_map(static fn (string $item): string => strtoupper($item), $items);
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
        $items = array_values(array_filter($items, static fn (string $item): bool => $item !== ''));

        return $items !== [] ? $items : null;
    }

    /**
     * @return array<int, float>
     */
    private function parseFloatGrid(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        $result = [];
        foreach ($parts as $part) {
            if (! is_numeric($part)) {
                continue;
            }
            $result[] = (float) $part;
        }

        return array_values(array_unique($result));
    }

    /**
     * @return array<int, bool>
     */
    private function parseBoolGrid(string $value): array
    {
        $parts = array_map('trim', explode(',', strtolower($value)));
        $parts = array_values(array_filter($parts, static fn (string $part): bool => $part !== ''));

        $result = [];
        foreach ($parts as $part) {
            if (in_array($part, ['1', 'true', 'yes'], true)) {
                $result[] = true;
            } elseif (in_array($part, ['0', 'false', 'no'], true)) {
                $result[] = false;
            }
        }

        return array_values(array_unique($result, SORT_REGULAR));
    }

    /**
     * @param  array<string, mixed>  $candidate
     * @param  array<string, mixed>  $current
     */
    private function isBetter(array $candidate, array $current): bool
    {
        $candidateSum = (float) ($candidate['sum_pct'] ?? 0.0);
        $currentSum = (float) ($current['sum_pct'] ?? 0.0);
        if ($candidateSum !== $currentSum) {
            return $candidateSum > $currentSum;
        }

        $candidateWr = (float) ($candidate['winrate'] ?? 0.0);
        $currentWr = (float) ($current['winrate'] ?? 0.0);
        if ($candidateWr !== $currentWr) {
            return $candidateWr > $currentWr;
        }

        return (int) ($candidate['signals'] ?? 0) > (int) ($current['signals'] ?? 0);
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
