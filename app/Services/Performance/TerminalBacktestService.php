<?php

namespace App\Services\Performance;

use App\Enums\SignalStatus;
use App\Models\Signal;
use App\Services\Api\RiskManagementProfileService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TerminalBacktestService
{
    public const MODEL_VERSION = 4;

    public function __construct(
        private readonly RiskManagementProfileService $riskManagement,
        private readonly PortfolioRiskPolicy $portfolioRisk,
    ) {}

    /**
     * @return array{
     *   trade_ids_desc: array<int, int>,
     *   balance_after_by_signal_id: array<int, float>,
     *   trade_notional_by_signal_id: array<int, float>,
     *   trade_margin_by_signal_id: array<int, float>,
     *   total_trades: int,
     *   wins: int,
     *   losses: int,
     *   longs: int,
     *   shorts: int
     * }
     */
    public function buildTradeHistory(
        string $scope,
        ?string $symbol,
        ?array $symbols,
        int $leverage,
        float $initialCapital = 10000.0,
        int $periodDays = 365,
        ?string $strategyKey = null,
    ): array {
        $dataset = $this->historyDataset(
            scope: $scope,
            symbol: $symbol,
            symbols: $symbols,
            leverage: $leverage,
            initialCapital: $initialCapital,
            periodDays: $periodDays,
            strategyKey: $strategyKey,
        );
        $sim = $dataset['simulation'];

        return [
            'trade_ids_desc' => $sim['trade_ids_desc'],
            'balance_after_by_signal_id' => $sim['balance_after_by_signal_id'],
            'trade_notional_by_signal_id' => $sim['trade_notional_by_signal_id'],
            'trade_margin_by_signal_id' => $sim['trade_margin_by_signal_id'],
            'total_trades' => $sim['closed'],
            'wins' => $sim['wins'],
            'losses' => $sim['losses'],
            'longs' => $sim['longs'],
            'shorts' => $sim['shorts'],
        ];
    }

    /**
     * @return array{
     *   history_total: int,
     *   visible_trade_ids: array<int, int>,
     *   next_limit: int,
     *   balance_after_by_signal_id: array<int, float>,
     *   trade_notional_by_signal_id: array<int, float>,
     *   trade_margin_by_signal_id: array<int, float>,
     *   total_trades: int,
     *   wins: int,
     *   losses: int,
     *   longs: int,
     *   shorts: int
     * }
     */
    public function buildTradeHistoryPage(
        string $scope,
        ?string $symbol,
        ?array $symbols,
        int $leverage,
        float $initialCapital = 10000.0,
        int $periodDays = 365,
        int $limit = 100,
        ?string $strategyKey = null,
    ): array {
        $history = $this->buildTradeHistory($scope, $symbol, $symbols, $leverage, $initialCapital, $periodDays, $strategyKey);
        $historyIdsDesc = array_values(array_map('intval', (array) ($history['trade_ids_desc'] ?? [])));
        $historyTotal = count($historyIdsDesc);
        $visibleTradeIds = array_slice($historyIdsDesc, 0, max(100, $limit));

        $balanceAfterBySignalId = [];
        $tradeNotionalBySignalId = [];
        $tradeMarginBySignalId = [];
        foreach ($visibleTradeIds as $id) {
            if (isset($history['balance_after_by_signal_id'][$id])) {
                $balanceAfterBySignalId[$id] = (float) $history['balance_after_by_signal_id'][$id];
            }
            if (isset($history['trade_notional_by_signal_id'][$id])) {
                $tradeNotionalBySignalId[$id] = (float) $history['trade_notional_by_signal_id'][$id];
            }
            if (isset($history['trade_margin_by_signal_id'][$id])) {
                $tradeMarginBySignalId[$id] = (float) $history['trade_margin_by_signal_id'][$id];
            }
        }

        return [
            'history_total' => $historyTotal,
            'visible_trade_ids' => $visibleTradeIds,
            'next_limit' => min($historyTotal, max(100, $limit) + 100),
            'balance_after_by_signal_id' => $balanceAfterBySignalId,
            'trade_notional_by_signal_id' => $tradeNotionalBySignalId,
            'trade_margin_by_signal_id' => $tradeMarginBySignalId,
            'total_trades' => (int) ($history['total_trades'] ?? 0),
            'wins' => (int) ($history['wins'] ?? 0),
            'losses' => (int) ($history['losses'] ?? 0),
            'longs' => (int) ($history['longs'] ?? 0),
            'shorts' => (int) ($history['shorts'] ?? 0),
        ];
    }

    /**
     * @return array{
     *   scope: string,
     *   symbol: string|null,
     *   symbols: array<int, string>,
     *   period_days: int,
     *   leverage: int,
     *   base_initial_capital: float,
     *   final_equity: float,
     *   roi_pct: float,
     *   max_drawdown_pct: float,
     *   opened: int,
     *   closed: int,
     *   wins: int,
     *   losses: int,
     *   flats: int,
     *   skipped_no_capital: int,
     *   skipped_invalid: int,
     *   gross_pnl: float,
     *   total_entry_fees: float,
     *   total_exit_fees: float,
     *   net_pnl_after_fees: float,
     *   avg_trade_pct: float,
     *   commission_rate_base: float,
     *   commission_rate_effective: float,
     *   commission_leverage_multiplier: float,
     *   profit_factor: ?float,
     *   win_rate: ?float,
     *   avg_r: ?float,
     *   max_drawdown: float,
     *   total_trades: int,
     *   equity_curve: array<int, float>
     * }
     */
    public function calculate(
        string $scope,
        ?string $symbol,
        ?array $symbols,
        int $leverage,
        float $initialCapital = 10000.0,
        int $periodDays = 365,
        ?string $strategyKey = null,
    ): array {
        $dataset = $this->historyDataset(
            scope: $scope,
            symbol: $symbol,
            symbols: $symbols,
            leverage: $leverage,
            initialCapital: $initialCapital,
            periodDays: $periodDays,
            strategyKey: $strategyKey,
        );
        $sim = $dataset['simulation'];

        return [
            'model_version' => self::MODEL_VERSION,
            'risk_model_version' => $this->riskManagement->version(),
            'strategy_key' => $strategyKey ?? app(StrategyRegistry::class)->activeKey(),
            'scope' => $dataset['scope'],
            'symbol' => $dataset['scope'] === 'symbol' ? $dataset['symbol'] : null,
            'symbols' => $dataset['scope'] === 'portfolio'
                ? $dataset['allowed_symbols']
                : ($dataset['scope'] === 'symbols' ? $dataset['symbols'] : ($dataset['symbol'] ? [$dataset['symbol']] : [])),
            'period_days' => $dataset['period_days'],
            'leverage' => $dataset['leverage'],
            'base_initial_capital' => $dataset['initial_capital'],
            'final_equity' => $sim['final_equity'],
            'roi_pct' => $sim['roi_pct'],
            'max_drawdown_pct' => $sim['max_drawdown_pct'],
            'opened' => $sim['opened'],
            'closed' => $sim['closed'],
            'wins' => $sim['wins'],
            'losses' => $sim['losses'],
            'flats' => $sim['flats'],
            'skipped_no_capital' => $sim['skipped_no_capital'],
            'skipped_invalid' => $sim['skipped_invalid'],
            'skipped_risk' => $sim['skipped_risk'],
            'risk_halted' => $sim['risk_halted'],
            'drawdown_basis' => 'realized_equity',
            'gross_pnl' => $sim['gross_pnl'],
            'total_entry_fees' => $sim['total_entry_fees'],
            'total_exit_fees' => $sim['total_exit_fees'],
            'net_pnl_after_fees' => $sim['net_pnl_after_fees'],
            'avg_trade_pct' => $sim['avg_trade_pct'],
            'commission_rate_base' => $sim['commission_rate_base'],
            'commission_rate_effective' => $sim['commission_rate_effective'],
            'commission_leverage_multiplier' => $sim['commission_leverage_multiplier'],
            'profit_factor' => $sim['profit_factor'],
            'win_rate' => $sim['closed'] > 0 ? ($sim['wins'] / $sim['closed']) : null,
            'avg_r' => $sim['avg_r'],
            'max_drawdown' => $sim['max_drawdown_pct'] / 100.0,
            'total_trades' => $sim['closed'],
            'equity_curve' => $sim['equity_curve'],
        ];
    }

    /**
     * @param  array<int, string>|null  $symbols
     * @return array{0:string,1:?string,2:array<int,string>,3:int,4:int,5:float,6:Collection<int, Signal>,7?:array<int, string>}
     */
    private function prepareInputs(
        string $scope,
        ?string $symbol,
        ?array $symbols,
        int $leverage,
        float $initialCapital,
        int $periodDays,
        bool $withAllowedSymbols = false,
        ?string $strategyKey = null,
    ): array {
        $scopeNormalized = in_array($scope, ['portfolio', 'symbol', 'symbols'], true) ? $scope : 'portfolio';
        $leverageBounds = $this->riskManagement->leverageBounds();
        $leverageNormalized = max($leverageBounds['min'], min($leverageBounds['max'], $leverage));
        $days = max(7, min(730, $periodDays));
        $capital = max(100.0, $initialCapital);
        $symbolUpper = $symbol !== null ? strtoupper(trim($symbol)) : null;
        $allowedSymbols = app(TerminalInstrumentService::class)->symbols();
        $symbolsNormalized = $symbols !== null
            ? array_values(array_unique(array_filter(array_map(static fn ($s) => strtoupper(trim((string) $s)), $symbols))))
            : [];
        $symbolsNormalized = array_values(array_intersect($symbolsNormalized, $allowedSymbols));

        $strategyKey ??= app(StrategyRegistry::class)->activeKey();
        app(StrategyRegistry::class)->definition($strategyKey);
        $query = Signal::query()
            ->where('strategy_key', $strategyKey)
            ->where('status', SignalStatus::Closed)
            ->whereNotNull('closed_at')
            ->whereNotNull('entry_at')
            ->whereNotNull('entry_price')
            ->whereNotNull('stop_loss')
            ->whereNotNull('close_price')
            ->where('entry_at', '>=', now()->subDays($days))
            ->where('closed_at', '<=', now());

        if ($scopeNormalized === 'portfolio') {
            $query->whereIn('symbol', $allowedSymbols);
        } elseif ($scopeNormalized === 'symbols') {
            if ($symbolsNormalized === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('symbol', $symbolsNormalized);
            }
        } elseif ($symbolUpper !== null) {
            $query->where('symbol', $symbolUpper);
        }

        /** @var Collection<int, Signal> $signals */
        $signals = $query
            ->orderBy('entry_at')
            ->orderBy('id')
            ->get([
                'id',
                'symbol',
                'direction',
                'entry_at',
                'entry_price',
                'stop_loss',
                'close_price',
                'closed_at',
                'status',
                'result_pct',
                'reward_risk',
            ]);

        if ($withAllowedSymbols) {
            return [$scopeNormalized, $symbolUpper, $symbolsNormalized, $leverageNormalized, $days, $capital, $signals, $allowedSymbols];
        }

        return [$scopeNormalized, $symbolUpper, $symbolsNormalized, $leverageNormalized, $days, $capital, $signals];
    }

    /**
     * @param  Collection<int, Signal>  $signals
     * @return array{
     *   final_equity: float,
     *   roi_pct: float,
     *   max_drawdown_pct: float,
     *   opened: int,
     *   closed: int,
     *   wins: int,
     *   losses: int,
     *   flats: int,
     *   skipped_no_capital: int,
     *   skipped_invalid: int,
     *   gross_pnl: float,
     *   total_entry_fees: float,
     *   total_exit_fees: float,
     *   net_pnl_after_fees: float,
     *   avg_trade_pct: float,
     *   commission_rate_base: float,
     *   commission_rate_effective: float,
     *   commission_leverage_multiplier: float,
     *   profit_factor: ?float,
     *   equity_curve: array<int, float>,
     *   trade_ids_desc: array<int, int>,
     *   balance_after_by_signal_id: array<int, float>,
     *   trade_notional_by_signal_id: array<int, float>,
     *   trade_margin_by_signal_id: array<int, float>,
     *   longs: int,
     *   shorts: int
     * }
     */
    private function simulate(Collection $signals, float $initialCapital, float $leverage): array
    {
        $commissionRateBase = (float) config('trading.commissionRate', 0.0004);
        // Leverage already affects margin; fees are charged on executed notional.
        $commissionLeverageMultiplier = 1.0;
        $commissionRate = $commissionRateBase;
        $ladder = $this->riskManagement->ladder();
        $minRiskPct = (float) $ladder['min_risk_percent'];
        $maxRiskPct = (float) $ladder['max_risk_percent'];
        $stepRiskPct = (float) $ladder['step_risk_percent'];
        $startRiskPct = (float) $ladder['start_risk_percent'];

        $levels = [];
        for ($risk = $minRiskPct; $risk <= $maxRiskPct + 1e-9; $risk += $stepRiskPct) {
            $levels[] = round($risk, 4);
        }
        $levelIndex = array_search($startRiskPct, $levels, true);
        if ($levelIndex === false) {
            $levelIndex = 0;
        }

        $events = [];
        foreach ($signals as $signal) {
            $entryTs = $signal->entry_at?->getTimestamp();
            $closeTs = $signal->closed_at?->getTimestamp();
            if (! $entryTs || ! $closeTs) {
                continue;
            }
            $events[] = ['ts' => $entryTs, 'type' => 'open', 'id' => (int) $signal->id, 'signal' => $signal];
            $events[] = ['ts' => $closeTs, 'type' => 'close', 'id' => (int) $signal->id, 'signal' => $signal];
        }

        usort($events, static function (array $left, array $right): int {
            if ($left['ts'] !== $right['ts']) {
                return $left['ts'] <=> $right['ts'];
            }
            if ($left['type'] !== $right['type']) {
                return $left['type'] === 'close' ? -1 : 1;
            }

            return strcmp($left['signal']->symbol, $right['signal']->symbol) ?: ($left['id'] <=> $right['id']);
        });

        $freeCash = $initialCapital;
        $lockedMargin = 0.0;
        $openPositions = [];

        $opened = 0;
        $closed = 0;
        $wins = 0;
        $losses = 0;
        $flats = 0;
        $skippedCapital = 0;
        $skippedInvalid = 0;
        $skippedRisk = 0;
        $riskHalted = false;

        $grossPnl = 0.0;
        $totalEntryFees = 0.0;
        $totalExitFees = 0.0;
        $sumTradePct = 0.0;
        $sumTradeR = 0.0;
        $sumPosNet = 0.0;
        $sumNegNet = 0.0;
        $longs = 0;
        $shorts = 0;
        $tradeIdsDesc = [];
        $balanceAfterBySignalId = [];
        $tradeNotionalBySignalId = [];
        $tradeMarginBySignalId = [];

        $peakEquity = $initialCapital;
        $maxDrawdownPct = 0.0;
        $equityCurve = [];

        foreach ($events as $event) {
            /** @var Signal $signal */
            $signal = $event['signal'];

            if ($event['type'] === 'open') {
                $entry = (float) $signal->entry_price;
                $stop = (float) $signal->stop_loss;
                if ($entry <= 0.0 || $stop <= 0.0) {
                    $skippedInvalid++;

                    continue;
                }

                $stopPct = abs($entry - $stop) / $entry;
                if ($stopPct <= 0.0 || ! is_finite($stopPct)) {
                    $skippedInvalid++;

                    continue;
                }

                $equity = $freeCash + $lockedMargin;
                $riskPct = $this->portfolioRisk->riskPercent($levels[$levelIndex] ?? $startRiskPct, $equity, $peakEquity) / 100.0;
                $riskAmount = $equity * $riskPct;
                $rejection = $this->portfolioRisk->rejectionReason(
                    $openPositions, $signal->symbol, $signal->direction->value, $equity, $riskAmount,
                );
                if ($riskHalted || $rejection !== null) {
                    $skippedRisk++;

                    continue;
                }
                // Reserve configured worst-case stop slippage and both side fees.
                // A gap beyond the stop remains unbounded by this approximation.
                $slipBps = (bool) config('trading.dynamicStopSlippageEnabled', false)
                    ? (float) config('trading.dynamicStopSlippageCapBps', 120)
                    : (float) config('trading.stopSlippageBps', 10);
                $exitReserve = ($slipBps + (float) config('trading.tradeSlippageBps', 0)
                    + (float) config('trading.spreadBps', 0)) / 10000;
                $exitLossFraction = $stopPct + $exitReserve * (1 + $stopPct);
                $lossFraction = $exitLossFraction + $commissionRate * (2 + $exitLossFraction);
                $desiredNotional = $riskAmount / $lossFraction;
                if (! is_finite($desiredNotional) || $desiredNotional <= 0.0) {
                    $skippedInvalid++;

                    continue;
                }

                $entryFee = $desiredNotional * $commissionRate;
                $requiredMargin = $desiredNotional / max(1.0, $leverage);
                $requiredCash = $requiredMargin + $entryFee;
                if ($freeCash + 1e-9 < $requiredCash) {
                    $skippedCapital++;

                    continue;
                }

                $freeCash -= $requiredCash;
                $lockedMargin += $requiredMargin;
                $totalEntryFees += $entryFee;
                $opened++;

                $openPositions[$signal->id] = [
                    'notional' => $desiredNotional,
                    'margin' => $requiredMargin,
                    'entry_fee' => $entryFee,
                    'initial_stop_risk' => $desiredNotional * $stopPct,
                    'symbol' => $signal->symbol,
                    'direction' => $signal->direction->value,
                    'reserved_risk' => $riskAmount,
                ];
            } else {
                $position = $openPositions[$signal->id] ?? null;
                if (! $position) {
                    continue;
                }

                $notional = (float) $position['notional'];
                $margin = (float) $position['margin'];

                $resultPct = $signal->resultPct();
                if ($resultPct === null) {
                    $entry = (float) $signal->entry_price;
                    $close = (float) $signal->close_price;
                    if ($entry > 0.0 && $close > 0.0) {
                        $resultPct = $signal->direction->value === 'SHORT'
                            ? (($entry - $close) / $entry)
                            : (($close - $entry) / $entry);
                    } else {
                        $resultPct = 0.0;
                    }
                }

                $tradeGross = $notional * $resultPct;
                $exitNotional = $notional * (float) $signal->close_price / (float) $signal->entry_price;
                $exitFee = $exitNotional * $commissionRate;
                $tradeNet = $tradeGross - $exitFee - $position['entry_fee'];

                // Entry fee was deducted on open; only gross PnL and exit fee enter cash here.
                $freeCash += $margin + $tradeGross - $exitFee;
                $lockedMargin -= $margin;
                $grossPnl += $tradeGross;
                $totalExitFees += $exitFee;
                $sumTradePct += ($tradeNet / $notional) * 100.0;
                $sumTradeR += $tradeNet / $position['initial_stop_risk'];
                $closed++;
                if ($signal->direction?->value === 'LONG') {
                    $longs++;
                } elseif ($signal->direction?->value === 'SHORT') {
                    $shorts++;
                }

                if ($tradeNet > 0.0) {
                    $wins++;
                    $sumPosNet += $tradeNet;
                    $levelIndex = min(count($levels) - 1, $levelIndex + 1);
                } elseif ($tradeNet < 0.0) {
                    $losses++;
                    $sumNegNet += $tradeNet;
                    $levelIndex = max(0, $levelIndex - 1);
                } else {
                    $flats++;
                }

                $balanceAfterBySignalId[(int) $signal->id] = round($freeCash + $lockedMargin, 2);
                $tradeNotionalBySignalId[(int) $signal->id] = round($notional, 2);
                $tradeMarginBySignalId[(int) $signal->id] = round($margin, 2);
                $tradeIdsDesc[] = (int) $signal->id;

                unset($openPositions[$signal->id]);
            }

            $equityNow = $freeCash + $lockedMargin;
            $peakEquity = max($peakEquity, $equityNow);
            $ddPct = $peakEquity > 0.0 ? (($peakEquity - $equityNow) / $peakEquity) * 100.0 : 0.0;
            $maxDrawdownPct = max($maxDrawdownPct, $ddPct);
            $riskHalted = $riskHalted || $this->portfolioRisk->shouldHalt($equityNow, $peakEquity);
            $equityCurve[] = round($equityNow, 2);
        }

        $finalEquity = $freeCash + $lockedMargin;
        $roiPct = $initialCapital > 0 ? (($finalEquity - $initialCapital) / $initialCapital) * 100.0 : 0.0;
        $profitFactor = $sumNegNet < 0.0 ? $sumPosNet / abs($sumNegNet) : null;

        return [
            'final_equity' => round($finalEquity, 2),
            'roi_pct' => round($roiPct, 4),
            'max_drawdown_pct' => round($maxDrawdownPct, 4),
            'opened' => $opened,
            'closed' => $closed,
            'wins' => $wins,
            'losses' => $losses,
            'flats' => $flats,
            'skipped_no_capital' => $skippedCapital,
            'skipped_invalid' => $skippedInvalid,
            'skipped_risk' => $skippedRisk,
            'risk_halted' => $riskHalted,
            'gross_pnl' => round($grossPnl, 2),
            'total_entry_fees' => round($totalEntryFees, 2),
            'total_exit_fees' => round($totalExitFees, 2),
            'net_pnl_after_fees' => round(($finalEquity - $initialCapital), 2),
            'avg_r' => $closed > 0 ? round($sumTradeR / $closed, 6) : null,
            'avg_trade_pct' => $closed > 0 ? round($sumTradePct / $closed, 4) : 0.0,
            'commission_rate_base' => round($commissionRateBase, 8),
            'commission_rate_effective' => round($commissionRate, 8),
            'commission_leverage_multiplier' => round($commissionLeverageMultiplier, 6),
            'profit_factor' => $profitFactor !== null ? round($profitFactor, 4) : null,
            'equity_curve' => $equityCurve,
            'trade_ids_desc' => array_reverse($tradeIdsDesc),
            'balance_after_by_signal_id' => $balanceAfterBySignalId,
            'trade_notional_by_signal_id' => $tradeNotionalBySignalId,
            'trade_margin_by_signal_id' => $tradeMarginBySignalId,
            'longs' => $longs,
            'shorts' => $shorts,
        ];
    }

    /**
     * @param  array<int, string>|null  $symbols
     * @return array{
     *   scope: string,
     *   symbol: string|null,
     *   symbols: array<int, string>,
     *   allowed_symbols: array<int, string>,
     *   leverage: int,
     *   initial_capital: float,
     *   period_days: int,
     *   simulation: array<string, mixed>
     * }
     */
    private function historyDataset(
        string $scope,
        ?string $symbol,
        ?array $symbols,
        int $leverage,
        float $initialCapital,
        int $periodDays,
        ?string $strategyKey = null,
    ): array {
        [$scopeNormalized, $symbolUpper, $symbolsNormalized, $leverageNormalized, $days, $capital, $signals, $allowedSymbols] = $this->prepareInputs(
            scope: $scope,
            symbol: $symbol,
            symbols: $symbols,
            leverage: $leverage,
            initialCapital: $initialCapital,
            periodDays: $periodDays,
            strategyKey: $strategyKey,
            withAllowedSymbols: true,
        );

        $cacheKey = $this->historyCacheKey(
            scope: $scopeNormalized,
            symbol: $symbolUpper,
            symbols: $symbolsNormalized,
            leverage: $leverageNormalized,
            initialCapital: $capital,
            periodDays: $days,
            strategyKey: $strategyKey,
        );

        /** @var array{
         *   scope: string,
         *   symbol: string|null,
         *   symbols: array<int, string>,
         *   allowed_symbols: array<int, string>,
         *   leverage: int,
         *   initial_capital: float,
         *   period_days: int,
         *   simulation: array<string, mixed>
         * } $payload */
        $payload = Cache::remember($cacheKey, now()->addSeconds(60), function () use (
            $scopeNormalized,
            $symbolUpper,
            $symbolsNormalized,
            $allowedSymbols,
            $leverageNormalized,
            $capital,
            $days,
            $signals,
        ): array {
            return [
                'scope' => $scopeNormalized,
                'symbol' => $symbolUpper,
                'symbols' => $symbolsNormalized,
                'allowed_symbols' => $allowedSymbols,
                'leverage' => $leverageNormalized,
                'initial_capital' => round($capital, 2),
                'period_days' => $days,
                'simulation' => $this->simulate($signals, $capital, (float) $leverageNormalized),
            ];
        });

        return $payload;
    }

    /**
     * @param  array<int, string>  $symbols
     */
    private function historyCacheKey(
        string $scope,
        ?string $symbol,
        array $symbols,
        int $leverage,
        float $initialCapital,
        int $periodDays,
        ?string $strategyKey = null,
    ): string {
        return 'terminal-backtest:history:v4:'.md5(json_encode([
            'scope' => $scope,
            'symbol' => $symbol,
            'symbols' => $symbols,
            'leverage' => $leverage,
            'risk_model_version' => $this->riskManagement->version(),
            'strategy_key' => $strategyKey ?? app(StrategyRegistry::class)->activeKey(),
            'capital' => round($initialCapital, 2),
            'days' => $periodDays,
        ], JSON_THROW_ON_ERROR));
    }
}
