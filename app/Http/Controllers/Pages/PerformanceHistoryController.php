<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Services\Performance\TerminalBacktestService;
use App\Services\Performance\TerminalPerformanceHistoryViewDataFactory;
use App\Services\Performance\TerminalPerformanceInputService;
use App\Services\Terminal\TerminalInstrumentService;
use App\Services\Trading\StrategyRegistry;
use Illuminate\Http\Request;

class PerformanceHistoryController extends Controller
{
    public function __invoke(
        Request $request,
        TerminalInstrumentService $instruments,
        TerminalPerformanceInputService $inputs,
        TerminalPerformanceHistoryViewDataFactory $historyViewData,
        TerminalBacktestService $backtests,
    ) {
        $registry = app(StrategyRegistry::class);
        $strategyKey = (string) $request->query('strategy', $registry->activeKey());
        abort_unless(array_key_exists($strategyKey, $registry->options()), 422);
        $symbol = (string) $request->query('symbol', '');
        $symbols = $inputs->extractSymbols($request, $symbol);
        $historyLimit = max(100, (int) $request->query('history_limit', 100));

        return response()->view('livewire.partials.performance-history', $historyViewData->make(
            symbolOptions: $instruments->symbols(),
            symbol: $symbol,
            symbols: $symbols,
            capital: (string) $request->query('capital', '10000'),
            leverage: (string) $request->query('leverage', '10'),
            period: (string) $request->query('period', '365'),
            historyLimit: $historyLimit,
            backtests: $backtests,
            strategyKey: $strategyKey,
        ));
    }
}
