<?php

namespace App\Console\Commands;

use App\Services\Performance\PerformanceSnapshotService;
use App\Services\Performance\TerminalBacktestService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Console\Command;

class RefreshTerminalBacktestsCommand extends Command
{
    protected $signature = 'performance:refresh-terminal-backtests
        {--days=7,14,30,60,90,180,365,730 : Trailing period in days or comma-separated list (e.g. 7,14,30,60,90,180,365,730)}
        {--strategy= : Registered strategy key}
        {--capital=10000 : Base capital for snapshot simulation}
        {--from-leverage=1 : Minimum leverage}
        {--to-leverage=20 : Maximum leverage}';

    protected $description = 'Precompute terminal backtests for portfolio and symbols.';

    public function handle(
        TerminalBacktestService $backtests,
        PerformanceSnapshotService $snapshots,
        TerminalInstrumentService $instruments,
    ): int {
        $strategyKey = $this->option('strategy') ?? app(StrategyRegistry::class)->activeKey();
        app(StrategyRegistry::class)->definition($strategyKey);
        $daysList = $this->parseDays((string) $this->option('days'));
        $capital = max(100.0, (float) $this->option('capital'));
        $fromLeverage = max(1, min(20, (int) $this->option('from-leverage')));
        $toLeverage = max(1, min(20, (int) $this->option('to-leverage')));
        if ($fromLeverage > $toLeverage) {
            [$fromLeverage, $toLeverage] = [$toLeverage, $fromLeverage];
        }

        $symbols = $instruments->symbols();
        $totalScopes = count($daysList) * ($toLeverage - $fromLeverage + 1) * (1 + count($symbols));
        $done = 0;

        $this->info("Refreshing {$totalScopes} backtest snapshots...");

        foreach ($daysList as $days) {
            $periodEnd = now();
            $periodStart = $periodEnd->copy()->subDays($days);
            for ($leverage = $fromLeverage; $leverage <= $toLeverage; $leverage++) {
                $portfolioMetrics = $backtests->calculate(
                    scope: 'portfolio',
                    symbol: null,
                    symbols: null,
                    leverage: $leverage,
                    initialCapital: $capital,
                    periodDays: $days,
                    strategyKey: $strategyKey,
                );

                $snapshots->upsert([
                    'scope' => 'terminal_backtest',
                    'scope_key' => "strategy:{$strategyKey}:portfolio:lev:{$leverage}:days:{$days}",
                    'metrics' => $portfolioMetrics,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                ]);
                $done++;
                $this->line("[{$done}/{$totalScopes}] portfolio lev {$leverage} days {$days}");

                foreach ($symbols as $symbol) {
                    $symbolMetrics = $backtests->calculate(
                        scope: 'symbol',
                        symbol: $symbol,
                        symbols: null,
                        leverage: $leverage,
                        initialCapital: $capital,
                        periodDays: $days,
                        strategyKey: $strategyKey,
                    );

                    $snapshots->upsert([
                        'scope' => 'terminal_backtest',
                        'scope_key' => "strategy:{$strategyKey}:symbol:{$symbol}:lev:{$leverage}:days:{$days}",
                        'metrics' => $symbolMetrics,
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                    ]);
                    $done++;
                    $this->line("[{$done}/{$totalScopes}] {$symbol} lev {$leverage} days {$days}");
                }
            }
        }

        $this->info('Terminal backtest snapshots refreshed.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function parseDays(string $raw): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $item): bool => $item !== ''));
        if ($parts === []) {
            return [365];
        }

        $days = [];
        foreach ($parts as $part) {
            $value = (int) $part;
            $days[] = max(7, min(730, $value));
        }

        $days = array_values(array_unique($days));
        sort($days);

        return $days;
    }
}
