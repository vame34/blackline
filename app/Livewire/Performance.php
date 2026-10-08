<?php

namespace App\Livewire;

use App\Services\Api\RiskManagementProfileService;
use App\Services\Performance\PerformanceSnapshotService;
use App\Services\Performance\TerminalBacktestService;
use App\Services\Performance\TerminalPerformanceHistoryViewDataFactory;
use App\Services\Performance\TerminalPerformanceInputService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class Performance extends Component
{
    #[On('display-timezone-synchronized')]
    public function refreshDisplayTimezone(): void {}

    private const ALL_SYMBOL = TerminalPerformanceInputService::ALL_SYMBOL;

    private const HISTORY_STEP = 100;

    public string $strategy = '';

    public string $symbol = '';

    public string $capital = '10000';

    public string $leverage = '10';

    public string $period = '365';

    public int $historyLimit = self::HISTORY_STEP;

    /** @var array<int, string> */
    public array $symbols = [];

    public string $preset = 'custom';

    public function mount(TerminalInstrumentService $instruments, TerminalPerformanceInputService $inputs): void
    {
        $registry = app(StrategyRegistry::class);
        $this->strategy = (string) request()->query('strategy', $registry->activeKey());
        abort_unless(array_key_exists($this->strategy, $registry->options()), 422);
        $this->symbol = (string) request()->query('symbol', $this->symbol);
        $this->symbols = $inputs->extractSymbols(request(), $this->symbol);
        $this->preset = (string) request()->query('preset', $this->preset);
        $this->capital = (string) request()->query('capital', $this->capital);
        $this->leverage = (string) request()->query('leverage', $this->leverage);
        $this->period = (string) request()->query('period', $this->period);
        $requestedHistoryLimit = (int) request()->query('history_limit', self::HISTORY_STEP);
        $this->historyLimit = max(self::HISTORY_STEP, $requestedHistoryLimit);
        $this->normalizeFilters($instruments->symbols());
    }

    public function showMoreHistory(): void
    {
        $this->historyLimit += self::HISTORY_STEP;
    }

    public function render(
        TerminalInstrumentService $instruments,
        PerformanceSnapshotService $snapshots,
        TerminalBacktestService $backtests,
        TerminalPerformanceInputService $inputs,
        TerminalPerformanceHistoryViewDataFactory $historyViewData,
        RiskManagementProfileService $riskManagement,
    ): View {
        $symbolOptions = $instruments->symbols();
        $selectedSymbols = $inputs->resolveSelectedSymbols($symbolOptions, $this->symbol, $this->symbols);
        [$selectedScope, $selectedScopeSymbol] = $inputs->resolveScope($selectedSymbols);
        $initialCapital = $inputs->normalizeCapital($this->capital);
        $leverage = $inputs->normalizeLeverage($this->leverage);
        $periodDays = $inputs->normalizePeriod($this->period);
        $snapshotKey = $selectedScope === 'portfolio'
            ? "portfolio:lev:{$leverage}:days:{$periodDays}"
            : ($selectedScope === 'symbol'
                ? "symbol:{$selectedScopeSymbol}:lev:{$leverage}:days:{$periodDays}"
                : null);

        $snapshotKey = $snapshotKey !== null ? "strategy:{$this->strategy}:{$snapshotKey}" : null;
        $snapshot = $snapshotKey !== null
            ? $snapshots->latest('terminal_backtest', $snapshotKey)
            : null;

        $baseMetrics = $snapshot?->metrics;
        if (! is_array($baseMetrics) || ($baseMetrics['model_version'] ?? null) !== TerminalBacktestService::MODEL_VERSION
            || ($baseMetrics['strategy_key'] ?? null) !== $this->strategy
            || ($baseMetrics['risk_model_version'] ?? null) !== $riskManagement->version()) {
            $baseMetrics = $backtests->calculate(
                scope: $selectedScope,
                symbol: $selectedScopeSymbol,
                symbols: $selectedScope === 'symbols' ? $selectedSymbols : null,
                leverage: $leverage,
                initialCapital: 10_000.0,
                periodDays: $periodDays,
                strategyKey: $this->strategy,
            );
        }

        $baseCapital = max(100.0, (float) ($baseMetrics['base_initial_capital'] ?? 10_000.0));
        $roiPct = (float) ($baseMetrics['roi_pct'] ?? 0.0);
        $finalEquity = $initialCapital * (1.0 + ($roiPct / 100.0));
        $capitalScale = $baseCapital > 0.0 ? ($initialCapital / $baseCapital) : 1.0;
        $baseCurve = is_array($baseMetrics['equity_curve'] ?? null) ? $baseMetrics['equity_curve'] : [];
        $scaledCurve = array_map(
            static fn ($value): float => round(((float) $value) * $capitalScale, 2),
            $baseCurve,
        );
        $scaledEntryFees = round(((float) ($baseMetrics['total_entry_fees'] ?? 0.0)) * $capitalScale, 2);
        $scaledExitFees = round(((float) ($baseMetrics['total_exit_fees'] ?? 0.0)) * $capitalScale, 2);
        $netPnl = round($finalEquity - $initialCapital, 2);

        $simulation = [
            'final_equity' => round($finalEquity, 2),
            'roi_pct' => round($roiPct, 4),
            'max_drawdown_pct' => round((float) ($baseMetrics['max_drawdown_pct'] ?? 0.0), 4),
            'opened' => (int) ($baseMetrics['opened'] ?? 0),
            'closed' => (int) ($baseMetrics['closed'] ?? 0),
            'wins' => (int) ($baseMetrics['wins'] ?? 0),
            'losses' => (int) ($baseMetrics['losses'] ?? 0),
            'flats' => (int) ($baseMetrics['flats'] ?? 0),
            'skipped_no_capital' => (int) ($baseMetrics['skipped_no_capital'] ?? 0),
            'skipped_invalid' => (int) ($baseMetrics['skipped_invalid'] ?? 0),
            'skipped_risk' => (int) ($baseMetrics['skipped_risk'] ?? 0),
            'risk_halted' => (bool) ($baseMetrics['risk_halted'] ?? false),
            'gross_pnl' => round($netPnl + $scaledEntryFees + $scaledExitFees, 2),
            'total_entry_fees' => $scaledEntryFees,
            'total_exit_fees' => $scaledExitFees,
            'net_pnl_after_fees' => $netPnl,
            'avg_trade_pct' => round((float) ($baseMetrics['avg_trade_pct'] ?? 0.0), 4),
            'profit_factor' => isset($baseMetrics['profit_factor']) ? (float) $baseMetrics['profit_factor'] : null,
            'equity_curve' => $scaledCurve,
            'commission_rate_base' => isset($baseMetrics['commission_rate_base']) ? (float) $baseMetrics['commission_rate_base'] : (float) config('trading.commissionRate', 0.0004),
            'commission_rate_effective' => isset($baseMetrics['commission_rate_effective']) ? (float) $baseMetrics['commission_rate_effective'] : (float) config('trading.commissionRate', 0.0004),
            'commission_leverage_multiplier' => isset($baseMetrics['commission_leverage_multiplier']) ? (float) $baseMetrics['commission_leverage_multiplier'] : 1.0,
            'base_initial_capital' => round($baseCapital, 2),
            'period_days' => (int) ($baseMetrics['period_days'] ?? $periodDays),
            'leverage' => $leverage,
            'scope' => $selectedScope,
            'symbol' => $selectedScopeSymbol,
            'symbols' => $selectedScope === 'symbols' ? $selectedSymbols : [],
        ];

        $metrics = [
            'total_trades' => $simulation['closed'],
            'wins' => $simulation['wins'],
            'losses' => $simulation['losses'],
            'win_rate' => isset($baseMetrics['win_rate']) ? (float) $baseMetrics['win_rate'] : ($simulation['closed'] > 0 ? ($simulation['wins'] / $simulation['closed']) : null),
            'avg_r' => isset($baseMetrics['avg_r']) ? (float) $baseMetrics['avg_r'] : null,
            'profit_factor' => $simulation['profit_factor'],
            'max_drawdown' => $simulation['max_drawdown_pct'] / 100.0,
            'equity_curve' => $simulation['equity_curve'],
        ];

        $history = $backtests->buildTradeHistory(
            scope: $selectedScope,
            symbol: $selectedScopeSymbol,
            symbols: $selectedScope === 'symbols' ? $selectedSymbols : null,
            leverage: $leverage,
            initialCapital: $initialCapital,
            periodDays: $periodDays,
            strategyKey: $this->strategy,
        );

        $historyClosed = (int) ($history['total_trades'] ?? 0);
        $historyWins = (int) ($history['wins'] ?? 0);
        $historyLosses = (int) ($history['losses'] ?? 0);
        $distributionLongCount = (int) ($history['longs'] ?? 0);
        $distributionShortCount = (int) ($history['shorts'] ?? 0);

        $metrics['total_trades'] = $historyClosed;
        $metrics['wins'] = $historyWins;
        $metrics['losses'] = $historyLosses;
        $metrics['win_rate'] = $historyClosed > 0 ? ($historyWins / $historyClosed) : null;

        $historyData = $historyViewData->make(
            symbolOptions: $symbolOptions,
            symbol: $this->symbol,
            symbols: $this->symbols,
            capital: $this->capital,
            leverage: $this->leverage,
            period: $this->period,
            historyLimit: $this->historyLimit,
            backtests: $backtests,
            strategyKey: $this->strategy,
        );

        $viewData = [
            'strategyOptions' => app(StrategyRegistry::class)->options(),
            'selectedStrategy' => $this->strategy,
            'metrics' => $metrics,
            'snapshot' => $snapshot,
            'distributionLongCount' => $distributionLongCount,
            'distributionShortCount' => $distributionShortCount,
            'symbolOptions' => array_merge([self::ALL_SYMBOL], $symbolOptions),
            'selectedScope' => $selectedScope,
            'selectedSymbol' => $selectedScopeSymbol ?? self::ALL_SYMBOL,
            'selectedSymbols' => $selectedSymbols,
            'selectedPreset' => $this->preset,
            'presetOptions' => $this->presetOptions(),
            'initialCapital' => $initialCapital,
            'selectedLeverage' => $leverage,
            'selectedPeriodDays' => $periodDays,
            'periodOptions' => [7, 14, 30, 60, 90, 180, 365, 730],
            'leverageOptions' => range(1, 20),
            'simulation' => $simulation,
        ];

        return view('livewire.performance', array_merge($viewData, $historyData))->layout('layouts.terminal');
    }

    private function normalizeNumber(string $raw, float $min, float $max, float $default): float
    {
        $normalized = str_replace(',', '.', trim($raw));
        if (! is_numeric($normalized)) {
            return $default;
        }

        $value = (float) $normalized;
        if (! is_finite($value)) {
            return $default;
        }

        return max($min, min($max, $value));
    }

    /**
     * @param  array<int, string>  $symbols
     */
    private function normalizeFilters(array $symbols): void
    {
        $allowedSymbols = array_merge([self::ALL_SYMBOL], $symbols);
        $selectedSymbols = array_values(array_unique(array_map(static fn (string $s) => strtoupper(trim($s)), $this->symbols)));
        $selectedSymbols = array_values(array_filter($selectedSymbols, static fn (string $s) => in_array($s, $allowedSymbols, true)));
        if ($selectedSymbols === []) {
            $selectedSymbols = [self::ALL_SYMBOL];
        }

        $this->preset = array_key_exists($this->preset, $this->presetOptions()) ? $this->preset : 'custom';
        if ($this->preset !== 'custom') {
            $preset = $this->presetOptions()[$this->preset];
            $presetSymbols = array_values(array_filter($preset['symbols'], static fn (string $s) => in_array($s, $symbols, true)));
            if ($presetSymbols !== []) {
                $selectedSymbols = $presetSymbols;
            }
            $this->leverage = (string) (int) $preset['leverage'];
        }

        if (in_array(self::ALL_SYMBOL, $selectedSymbols, true)) {
            $selectedSymbols = [self::ALL_SYMBOL];
        }
        $this->symbols = $selectedSymbols;
        $this->symbol = $selectedSymbols[0] ?? self::ALL_SYMBOL;
        $this->leverage = (string) (int) round($this->normalizeNumber($this->leverage, 1.0, 20.0, 10.0));
        $this->capital = (string) round($this->normalizeNumber($this->capital, 100.0, 1_000_000_000.0, 10_000.0), 2);
        $this->period = (string) $this->normalizePeriod($this->period);
    }

    private function normalizePeriod(string $raw): int
    {
        $value = (int) trim($raw);
        $allowed = [7, 14, 30, 60, 90, 180, 365, 730];

        return in_array($value, $allowed, true) ? $value : 365;
    }

    /**
     * @return array<string, array{label:string, leverage:int, period:int, symbols:array<int,string>}>
     */
    private function presetOptions(): array
    {
        return [
            'custom' => [
                'label' => 'custom.performance.preset_custom',
                'leverage' => 10,
                'period' => 365,
                'symbols' => [],
            ],
            'conservative' => [
                'label' => 'custom.performance.preset_conservative',
                'leverage' => 3,
                'period' => 365,
                'symbols' => ['BTCUSDT', 'ETHUSDT', 'BNBUSDT', 'LTCUSDT', 'TRXUSDT'],
            ],
            'aggressive' => [
                'label' => 'custom.performance.preset_aggressive',
                'leverage' => 20,
                'period' => 365,
                'symbols' => ['SEIUSDT', 'FILUSDT', 'AVAXUSDT', 'MANAUSDT', 'ALGOUSDT', 'SNXUSDT', 'INJUSDT', 'SUSHIUSDT', 'DOGEUSDT'],
            ],
        ];
    }
}
