<?php

namespace App\Services\Performance;

use App\Models\Signal;
use Illuminate\Support\Collection;

class TerminalPerformanceHistoryViewDataFactory
{
    public function __construct(
        private readonly TerminalPerformanceInputService $inputs,
    ) {}

    /**
     * @param  array<int, string>  $symbolOptions
     * @param  array<int, string>  $symbols
     * @return array<string, mixed>
     */
    public function make(
        array $symbolOptions,
        string $symbol,
        array $symbols,
        string $capital,
        string $leverage,
        string $period,
        int $historyLimit,
        TerminalBacktestService $backtests,
        ?string $strategyKey = null,
    ): array {
        $selectedSymbols = $this->inputs->resolveSelectedSymbols($symbolOptions, $symbol, $symbols);
        [$selectedScope, $selectedScopeSymbol] = $this->inputs->resolveScope($selectedSymbols);
        $initialCapital = $this->inputs->normalizeCapital($capital);
        $selectedLeverage = $this->inputs->normalizeLeverage($leverage);
        $selectedPeriodDays = $this->inputs->normalizePeriod($period);

        $history = $backtests->buildTradeHistoryPage(
            scope: $selectedScope,
            symbol: $selectedScopeSymbol,
            symbols: $selectedScope === 'symbols' ? $selectedSymbols : null,
            leverage: $selectedLeverage,
            initialCapital: $initialCapital,
            periodDays: $selectedPeriodDays,
            limit: $historyLimit,
            strategyKey: $strategyKey,
        );

        $visibleHistoryIds = array_values(array_map('intval', (array) ($history['visible_trade_ids'] ?? [])));
        $signalsById = Signal::query()
            ->select([
                'id',
                'symbol',
                'direction',
                'entry_price',
                'close_price',
                'reward_risk',
                'closed_at',
                'status',
                'result_pct',
            ])
            ->whereIn('id', $visibleHistoryIds)
            ->get()
            ->keyBy('id');

        /** @var Collection<int, Signal> $closedSignals */
        $closedSignals = collect($visibleHistoryIds)
            ->map(static fn (int $id) => $signalsById->get($id))
            ->filter()
            ->values();

        return [
            'closedSignals' => $closedSignals,
            'historyTotal' => (int) ($history['history_total'] ?? 0),
            'historyVisible' => $closedSignals->count(),
            'historyNextLimit' => (int) ($history['next_limit'] ?? $closedSignals->count()),
            'historyBalanceBySignalId' => (array) ($history['balance_after_by_signal_id'] ?? []),
            'historyTradeNotionalBySignalId' => (array) ($history['trade_notional_by_signal_id'] ?? []),
            'historyTradeMarginBySignalId' => (array) ($history['trade_margin_by_signal_id'] ?? []),
            'selectedLeverage' => $selectedLeverage,
            'coinIconMap' => $this->inputs->coinIconMap(),
        ];
    }
}
