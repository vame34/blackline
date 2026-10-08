@php
    $winRate = $metrics['win_rate'] ?? null;
    $avgR = $metrics['avg_r'] ?? null;
    $profitFactor = $metrics['profit_factor'] ?? null;
    $maxDrawdown = $metrics['max_drawdown'] ?? null;
    $totalTrades = (int) ($metrics['total_trades'] ?? 0);
    $wins = (int) ($metrics['wins'] ?? 0);
    $losses = (int) ($metrics['losses'] ?? 0);

    $winRatePct = $winRate !== null ? $winRate * 100 : null;
    $maxDrawdownPct = $maxDrawdown !== null ? $maxDrawdown * 100 : null;
    $avgTradePct = $simulation['avg_trade_pct'] ?? null;
    $finalEquity = (float) ($simulation['final_equity'] ?? $initialCapital);
    $roiPct = (float) ($simulation['roi_pct'] ?? 0.0);
    $netPnl = (float) ($simulation['net_pnl_after_fees'] ?? 0.0);
    $maxDdSim = (float) ($simulation['max_drawdown_pct'] ?? 0.0);
    $skippedByCapital = (int) ($simulation['skipped_no_capital'] ?? 0);
    $periodDays = (int) ($simulation['period_days'] ?? ($selectedPeriodDays ?? 365));
    $overviewDate = app(\App\Services\Preferences\DisplayTimezone::class)->format($snapshot?->period_end ?? now(), 'd.m.Y');
    $coinIconMap = $coinIconMap ?? [
        'BTC' => asset('images/coins/btc.png'),
        'SOL' => asset('images/coins/sol.png'),
        'ADA' => asset('images/coins/ada.png'),
        'LINK' => asset('images/coins/link.png'),
        'BCH' => asset('images/coins/bch.png'),
        'BNB' => asset('images/coins/bnb.png'),
        'TRX' => asset('images/coins/trx.png'),
        'RUNE' => asset('images/coins/rune.png'),
        'MANA' => asset('images/coins/mana.png'),
        'XRP' => asset('images/coins/xrp.png'),
        'XLM' => asset('images/coins/xlm.png'),
        'AVAX' => asset('images/coins/avax.png'),
        'LTC' => asset('images/coins/ltc.png'),
        'SAND' => asset('images/coins/sand.png'),
        'DOGE' => asset('images/coins/doge.png'),
        'DOT' => asset('images/coins/dot.png'),
        'ATOM' => asset('images/coins/atom.png'),
        'FIL' => asset('images/coins/fil.png'),
        'ETH' => asset('images/coins/eth.png'),
        'PEPE' => asset('images/coins/pepe.jpg'),
        'INJ' => asset('images/coins/inj.png'),
        'APT' => asset('images/coins/apt.png'),
        'ALGO' => asset('images/coins/algo.png'),
        'COMP' => asset('images/coins/comp.png'),
        'KAVA' => asset('images/coins/kava.png'),
        'ICP' => asset('images/coins/icp.png'),
        'ZRX' => asset('images/coins/zrx.png'),
        'CHZ' => asset('images/coins/chz.png'),
        'SUSHI' => asset('images/coins/sushi.png'),
        'SEI' => asset('images/coins/sei.png'),
        'SNX' => asset('images/coins/snx.png'),
    ];
    $longCount = (int) ($distributionLongCount ?? 0);
    $shortCount = (int) ($distributionShortCount ?? 0);
    $sumDir = max(1, $longCount + $shortCount);
    $longPct = ($longCount / $sumDir) * 100;
    $shortPct = ($shortCount / $sumDir) * 100;
    $commissionPct = ((float) ($simulation['commission_rate_effective'] ?? config('trading.commissionRate', 0.0004))) * 100;
    $apiRestUrl = url('/api/v1/signals');
    $apiStreamUrl = url('/api/v1/signals/stream');
    $selectedSymbolsEffective = array_values(array_filter($selectedSymbols ?? [], static fn ($item) => $item !== '__ALL__'));
    $selectedSymbolsCount = count($selectedSymbolsEffective);
    $selectedScopeLabel = $selectedScope === 'portfolio'
        ? __('custom.performance.scope_portfolio')
        : ($selectedScope === 'symbols'
            ? __('custom.performance.scope_symbols', ['count' => $selectedSymbolsCount])
            : $selectedSymbol);
    $selectedPresetLabel = isset($presetOptions[$selectedPreset]['label'])
        ? __($presetOptions[$selectedPreset]['label'])
        : ucfirst((string) $selectedPreset);
    $selectedPeriodLabel = match ((int) $selectedPeriodDays) {
        7 => __('custom.performance.period_7d'),
        14 => __('custom.performance.period_14d'),
        30 => __('custom.performance.period_30d'),
        60 => __('custom.performance.period_60d'),
        90 => __('custom.performance.period_90d'),
        180 => __('custom.performance.period_180d'),
        365 => __('custom.performance.period_365d'),
        730 => __('custom.performance.period_730d'),
        default => $selectedPeriodDays . 'd',
    };
    $periodEndDate = ($snapshot?->period_end?->copy() ?? now())->setTimezone(app(\App\Services\Preferences\DisplayTimezone::class)->resolved());
    $periodStartDate = $periodEndDate->copy()->subDays(max(1, (int) $selectedPeriodDays) - 1);
    $presetUi = in_array($selectedPreset, ['conservative', 'custom', 'aggressive'], true) ? $selectedPreset : 'custom';
    $avgRewardRisk = $closedSignals
        ->filter(static fn ($signal) => $signal->reward_risk !== null)
        ->avg(static fn ($signal) => (float) $signal->reward_risk);
    $equityCurve = is_array($metrics['equity_curve'] ?? null) ? array_values(array_map('floatval', $metrics['equity_curve'])) : [];
    $drawdownPeakAmount = null;
    $drawdownTroughAmount = null;
    if (count($equityCurve) > 1) {
        $peak = $equityCurve[0];
        $peakAtWorst = $peak;
        $troughAtWorst = $peak;
        $worstDd = 0.0;
        foreach ($equityCurve as $value) {
            if ($value > $peak) {
                $peak = $value;
            }
            if ($peak <= 0.0) {
                continue;
            }
            $dd = ($peak - $value) / $peak;
            if ($dd > $worstDd) {
                $worstDd = $dd;
                $peakAtWorst = $peak;
                $troughAtWorst = $value;
            }
        }
        if ($worstDd > 0.0) {
            $drawdownPeakAmount = $peakAtWorst;
            $drawdownTroughAmount = $troughAtWorst;
        }
    }
@endphp

<style>
    .performance-reference body {
        background-color: #000000;
        color: #ffffff;
    }

    .performance-reference .skew-btn {
        transform: skew(-12deg);
        transition: all 0.3s ease;
    }

    .performance-reference .skew-btn-content {
        transform: skew(12deg);
    }

    .performance-reference .nav-link {
        position: relative;
    }

    .performance-reference .nav-link::after {
        content: '';
        position: absolute;
        bottom: -4px;
        left: 0;
        width: 0;
        height: 2px;
        background-color: #00ff88;
        transition: width 0.3s ease;
        box-shadow: 0 0 8px #00ff88;
    }

    .performance-reference .nav-link:hover::after,
    .performance-reference .nav-link.active::after {
        width: 100%;
    }

    .performance-reference .nav-link.active {
        color: #00ff88;
        text-shadow: 0 0 10px rgba(0, 255, 136, 0.4);
    }

    .performance-reference .glass-panel {
        background: rgba(10, 10, 10, 0.6);
        backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .performance-reference .glass-panel:hover {
        border-color: rgba(0, 255, 136, 0.4);
        box-shadow: 0 0 15px rgba(0, 255, 136, 0.1);
    }

    .performance-reference .chart-grid {
        background-image: linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        background-size: 40px 40px;
    }

    .performance-reference ::-webkit-scrollbar {
        width: 8px;
        height: 8px;
    }

    .performance-reference ::-webkit-scrollbar-track {
        background: #000;
    }

    .performance-reference ::-webkit-scrollbar-thumb {
        background: #333;
    }

    .performance-reference ::-webkit-scrollbar-thumb:hover {
        background: #00ff88;
    }

    .performance-reference .pulse-dot {
        display: inline-block;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background-color: #00ff88;
        box-shadow: 0 0 0 rgba(0, 255, 136, 0.4);
        animation: pulse-green 2s infinite;
    }

    @keyframes pulse-green {
        0% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(0, 255, 136, 0.7);
        }

        70% {
            transform: scale(1);
            box-shadow: 0 0 0 6px rgba(0, 255, 136, 0);
        }

        100% {
            transform: scale(0.95);
            box-shadow: 0 0 0 0 rgba(0, 255, 136, 0);
        }
    }

    .performance-reference .conic-chart {
        border-radius: 50%;
    }

    .performance-reference .equity-canvas {
        width: 100%;
        height: 100%;
        display: block;
        cursor: crosshair;
    }

    .performance-reference .equity-tooltip {
        position: absolute;
        pointer-events: none;
        min-width: 140px;
        transform: translate(-50%, calc(-100% - 12px));
        border: 1px solid rgba(0, 255, 136, 0.35);
        background: rgba(0, 0, 0, 0.88);
        padding: 8px 10px;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: 11px;
        line-height: 1.35;
        color: #d1d5db;
        z-index: 20;
        backdrop-filter: blur(4px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.35);
    }

    .performance-reference .equity-tooltip-hidden {
        opacity: 0;
        visibility: hidden;
    }

    .performance-reference .equity-loading {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
        background: linear-gradient(180deg, rgba(7, 7, 7, 0.65) 0%, rgba(10, 10, 10, 0.4) 100%);
        transition: opacity 0.25s ease;
        z-index: 12;
    }

    .performance-reference .equity-loading-hidden {
        opacity: 0;
        visibility: hidden;
    }

    .performance-reference .equity-loading-dot {
        width: 9px;
        height: 9px;
        border-radius: 9999px;
        background: #00ff88;
        box-shadow: 0 0 14px rgba(0, 255, 136, 0.6);
        animation: pulse-green 1.3s infinite;
    }

    .performance-reference .equity-logo-watermark {
        position: absolute;
        inset: 0;
        pointer-events: none;
        background-image: url('/images/blackline-logo-2026.png');
        background-repeat: no-repeat;
        background-position: center;
        background-size: min(48%, 420px);
        opacity: 0.08;
        filter: grayscale(1) brightness(0.9);
        z-index: 0;
    }

    @media (max-width: 768px) {
        .performance-reference .equity-logo-watermark {
            background-size: min(68%, 260px);
            opacity: 0.06;
        }
    }
</style>

<div class="performance-reference min-h-screen flex flex-col font-sans selection:bg-[#00ff88] selection:text-black">
    <main class="relative container mx-auto w-full flex-grow space-y-8 p-6 pt-32 lg:p-10 lg:pt-32">
        <div class="fixed top-20 left-1/4 w-[500px] h-[500px] bg-[#00ff88]/5 blur-[120px] rounded-full pointer-events-none -z-10"></div>
        <div class="fixed bottom-0 right-0 w-[600px] h-[600px] bg-[#00ff88]/5 blur-[150px] rounded-full pointer-events-none -z-10"></div>

        <header class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-2">
            <div>
                <h1 class="text-5xl md:text-6xl font-black italic text-white tracking-tighter">{{ __('custom.performance.title') }}</h1>
                <p class="font-mono text-gray-500 text-sm mt-2 uppercase tracking-widest pl-1">
                    {{ __('custom.performance.overview_updated', ['date' => $overviewDate]) }}
                </p>
            </div>

        </header>

        <section class="glass-panel p-8 lg:p-10">
            <header class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 sm:gap-6 lg:gap-8 xl:gap-16 relative">
                <div class="space-y-4 sm:space-y-6 max-w-2xl relative z-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#00E676]/5 border border-[#00E676]/20">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#00E676] opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-[#00E676]"></span>
                        </span>
                        <span class="text-xs font-mono font-medium tracking-widest text-[#00E676] uppercase">Backtest Engine v2.0</span>
                    </div>

                    <h2 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold text-white leading-[1.1] tracking-tight">
                        Результат стратегии за
                        <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#00E676] to-emerald-400">последние {{ $periodDays }} дней</span>
                    </h2>

                    <p class="text-base sm:text-lg text-[#888888] leading-relaxed max-w-2xl">
                        Детальная симуляция с учетом рыночных комиссий ({{ number_format($commissionPct, 4, '.', '') }}%), доступного маржинального обеспечения и кредитного плеча. Входы пропускаются при недостатке свободных средств.
                    </p>

                    <div class="flex flex-wrap items-center gap-3 sm:gap-4 pt-2">
                        <div class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#121212] border border-[#2A2A2A] text-base text-[#888888]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 002-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            <span>{{ $selectedScopeLabel }}</span>
                        </div>
                        <div class="flex items-center gap-2 px-5 py-2.5 rounded-lg bg-[#121212] border border-[#2A2A2A] text-base text-[#888888]">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span>Капитал: <strong class="text-white font-mono">${{ number_format($initialCapital, 0, '.', ' ') }}</strong></span>
                        </div>
                    </div>
                </div>

                <div class="w-full xl:w-[460px] shrink-0 relative group z-10">
                    <div class="relative glass-panel border border-[#2A2A2A] rounded-xl sm:rounded-2xl p-4 sm:p-6 lg:p-8 shadow-[0_4px_20px_rgba(0,0,0,0.5)] flex flex-col items-center justify-center text-center overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-[#00E676]/5 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>

                        <h3 class="text-xs font-mono font-bold tracking-widest text-[#888888] uppercase mb-3 sm:mb-4">Гипотетическая Прибыль</h3>

                        <div class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-mono font-extrabold text-[#00E676] mb-4 sm:mb-6 drop-shadow-[0_0_20px_rgba(0,230,118,0.5)] tracking-tighter">
                            {{ ($roiPct >= 0 ? '+' : '') . number_format($roiPct, 1) }}<span class="text-4xl text-[#00E676]/80">%</span>
                        </div>

                        <div class="w-full rounded-lg border border-[#2A2A2A] bg-[#0A0A0A]/40 p-3 text-left">
                            <div class="text-[11px] font-mono text-gray-400">{{ __('custom.performance.data_source') }}</div>
                            <div class="mt-1 text-[11px] font-mono text-gray-400">{{ __('custom.performance.fees_included', ['rate' => number_format($commissionPct, 4, '.', '')]) }}</div>
                            <div class="mt-1 text-[11px] font-mono text-gray-400">{{ __('custom.performance.slippage_included') }}</div>
                        </div>

                        <div class="w-full h-px bg-gradient-to-r from-transparent via-[#404040] to-transparent mb-6"></div>

                        <div class="w-full space-y-3">
                            <div class="flex justify-between items-center px-3 sm:px-4 py-2.5 sm:py-3 bg-[#0A0A0A]/50 rounded-lg sm:rounded-xl border border-[#2A2A2A]">
                                <span class="text-sm text-[#888888]">Финальный баланс</span>
                                <span class="text-lg font-mono font-semibold text-white drop-shadow-md">${{ number_format($finalEquity, 2, '.', ' ') }}</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2 sm:gap-3">
                                <div class="flex flex-col items-center justify-center py-2 px-2 sm:px-3 bg-[#0A0A0A]/30 rounded-lg border border-[#2A2A2A]">
                                    <span class="text-xs text-[#555555] uppercase mb-1">Макс. просадка</span>
                                    <span class="text-sm font-mono font-medium text-[#FF4444] drop-shadow-[0_0_8px_rgba(255,68,68,0.3)]">-{{ number_format(abs($maxDdSim), 2, '.', '') }}%</span>
                                </div>
                                <div class="flex flex-col items-center justify-center py-2 px-2 sm:px-3 bg-[#0A0A0A]/30 rounded-lg border border-[#2A2A2A]">
                                    <span class="text-xs text-[#555555] uppercase mb-1">Пропущено входов</span>
                                    <span class="text-sm font-mono font-medium text-white">{{ $skippedByCapital }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <form method="GET" action="{{ route('performance.terminal') }}" class="space-y-10 mt-10" data-performance-controls>
                <label class="block text-xs font-mono uppercase text-gray-400">
                    <span class="mb-2 block">{{ __('ui.signals.strategy') }}</span>
                    <select name="strategy" class="w-full max-w-sm border border-white/10 bg-black p-3 text-white focus:border-[#00ff88]">
                        @foreach($strategyOptions as $key => $label)
                            <option value="{{ $key }}" @selected($selectedStrategy === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="history_limit" value="{{ $historyVisible }}">

                <div class="flex items-center gap-3 border-b border-[#2A2A2A] pb-5">
                    <div class="w-1.5 h-8 bg-[#00E676] rounded-full shadow-[0_0_10px_rgba(0,230,118,0.5)]"></div>
                    <h3 class="text-xl sm:text-2xl font-semibold text-white tracking-tight">Конфигурация параметров</h3>
                </div>

                <div class="space-y-5">
                    <label class="block text-sm font-mono font-semibold text-[#888888] uppercase tracking-widest">1. Выбор сетапа</label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
                        <label for="preset-conservative" class="{{ $presetUi === 'conservative' ? 'relative bg-[#1A1A1A] border border-[#00E676] rounded-xl p-6 cursor-pointer shadow-[0_0_20px_rgba(0,230,118,0.25)] transition-all duration-300 transform sm:scale-[1.02]' : 'relative group bg-[#121212] border border-[#2A2A2A] rounded-xl p-6 cursor-pointer hover:border-[#888888] hover:bg-[#222222] transition-all duration-300' }}">
                            <input id="preset-conservative" class="sr-only" type="radio" name="preset" value="conservative" {{ $presetUi === 'conservative' ? 'checked' : '' }}>
                            <div class="flex justify-between items-start mb-5">
                                <div class="{{ $presetUi === 'conservative' ? 'w-11 h-11 rounded-lg bg-[#00E676]/10 border border-[#00E676]/20 flex items-center justify-center text-[#00E676]' : 'w-11 h-11 rounded-lg bg-[#0A0A0A] border border-[#2A2A2A] flex items-center justify-center text-[#888888] group-hover:text-white transition-colors' }}">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                </div>
                                <span class="px-3 py-1 rounded bg-[#1A1A1A] border border-[#2A2A2A] text-sm font-mono font-medium text-white">x5</span>
                            </div>
                            <h4 class="text-xl font-medium text-white mb-2">Консервативный</h4>
                            <p class="text-sm text-[#888888] mb-7 min-h-[40px]">Минимальные риски, фокус на сохранении капитала.</p>
                            <div class="space-y-2 mt-auto">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[#888888]">Уровень риска</span>
                                    <span class="text-[#00E676]">Низкий</span>
                                </div>
                                <div class="h-2 w-full bg-[#0A0A0A] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#00E676] w-1/4 rounded-full"></div>
                                </div>
                            </div>
                        </label>

                        <label for="preset-custom" class="{{ $presetUi === 'custom' ? 'relative bg-[#1A1A1A] border border-[#00E676] rounded-xl p-6 cursor-pointer shadow-[0_0_20px_rgba(0,230,118,0.25)] transition-all duration-300 transform sm:scale-[1.02]' : 'relative group bg-[#121212] border border-[#2A2A2A] rounded-xl p-6 cursor-pointer hover:border-[#888888] hover:bg-[#222222] transition-all duration-300' }}">
                            <input id="preset-custom" class="sr-only" type="radio" name="preset" value="custom" {{ $presetUi === 'custom' ? 'checked' : '' }}>
                            <div class="flex justify-between items-start mb-5">
                                <div class="{{ $presetUi === 'custom' ? 'w-11 h-11 rounded-lg bg-[#00E676]/10 border border-[#00E676]/20 flex items-center justify-center text-[#00E676]' : 'w-11 h-11 rounded-lg bg-[#0A0A0A] border border-[#2A2A2A] flex items-center justify-center text-[#888888] group-hover:text-white transition-colors' }}">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </div>
                            </div>
                            <h4 class="text-xl font-medium text-white mb-2">Кастомный</h4>
                            <p class="text-sm text-[#888888] mb-7 min-h-[40px]">Полный контроль над параметрами симуляции.</p>
                            <div class="space-y-2 mt-auto">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[#888888]">Уровень риска</span>
                                    <span class="text-white font-mono">Настраиваемый</span>
                                </div>
                                <div class="h-2 w-full bg-[#0A0A0A] rounded-full flex overflow-hidden">
                                    <div class="h-full bg-[#2A2A2A] w-1/3 border-r border-[#0A0A0A]"></div>
                                    <div class="h-full bg-[#888888] w-1/3 border-r border-[#0A0A0A]"></div>
                                    <div class="h-full bg-[#2A2A2A] w-1/3"></div>
                                </div>
                            </div>
                        </label>

                        <label for="preset-aggressive" class="{{ $presetUi === 'aggressive' ? 'relative bg-[#1A1A1A] border border-[#00E676] rounded-xl p-6 cursor-pointer shadow-[0_0_20px_rgba(0,230,118,0.25)] transition-all duration-300 transform sm:scale-[1.02]' : 'relative group bg-[#121212] border border-[#2A2A2A] rounded-xl p-6 cursor-pointer hover:border-[#888888] hover:bg-[#222222] transition-all duration-300' }}">
                            <input id="preset-aggressive" class="sr-only" type="radio" name="preset" value="aggressive" {{ $presetUi === 'aggressive' ? 'checked' : '' }}>
                            <div class="flex justify-between items-start mb-5">
                                <div class="{{ $presetUi === 'aggressive' ? 'w-11 h-11 rounded-lg bg-[#00E676]/10 border border-[#00E676]/20 flex items-center justify-center text-[#00E676]' : 'w-11 h-11 rounded-lg bg-[#0A0A0A] border border-[#2A2A2A] flex items-center justify-center text-[#888888] group-hover:text-[#FF4444] transition-colors' }}">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <span class="px-3 py-1 rounded bg-[#1A1A1A] border border-[#2A2A2A] text-sm font-mono font-medium text-white">x20</span>
                            </div>
                            <h4 class="text-xl font-medium text-white mb-2">Агрессивный</h4>
                            <p class="text-sm text-[#888888] mb-7 min-h-[40px]">Максимальная доходность, высокие просадки.</p>
                            <div class="space-y-2 mt-auto">
                                <div class="flex justify-between items-center text-sm">
                                    <span class="text-[#888888]">Уровень риска</span>
                                    <span class="text-[#FF4444]">Высокий</span>
                                </div>
                                <div class="h-2 w-full bg-[#0A0A0A] rounded-full overflow-hidden">
                                    <div class="h-full bg-[#FF4444] w-5/6 rounded-full shadow-[0_0_10px_rgba(255,68,68,0.5)]"></div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                    <div class="xl:col-span-7 2xl:col-span-8 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-2">
                            <label for="performance-symbols" class="block text-xs font-mono font-semibold text-[#888888] uppercase tracking-widest">2. Выбор активов</label>
                            <div class="flex items-center gap-4 text-xs font-mono">
                                <button type="button" data-symbols-select-all class="text-[#00E676] hover:text-[#00FF88] font-medium transition-colors">Выбрать все</button>
                                <span class="w-px h-3 bg-[#2A2A2A]"></span>
                                <button type="button" data-symbols-clear class="text-[#555555] hover:text-white transition-colors">Очистить</button>
                            </div>
                        </div>

                        <div class="bg-[#121212] border border-[#2A2A2A] rounded-xl p-2 sm:p-3 min-h-[56px] sm:min-h-[64px] flex flex-col gap-2 sm:gap-2.5 transition-colors hover:border-[#404040] focus-within:border-[#00E676] focus-within:shadow-[0_0_20px_rgba(0,230,118,0.15)]" data-symbols-picker>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    data-symbols-dropdown-toggle
                                    class="w-full h-11 rounded-lg border border-[#2A2A2A] bg-[#0A0A0A]/70 px-3 sm:px-4 text-left flex items-center justify-between gap-3 hover:border-[#404040] transition-colors"
                                    aria-expanded="false"
                                >
                                    <span class="text-sm font-mono text-[#888888] truncate" data-symbols-dropdown-label>Выберите монеты</span>
                                    <svg class="w-4 h-4 text-[#777777] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </div>
                            <div class="flex flex-wrap gap-2 sm:gap-2.5 items-center" data-symbols-chips></div>
                            <div data-symbols-dropdown class="hidden w-full border border-[#2A2A2A] rounded-xl bg-[#0A0A0A]/95 p-2 sm:p-3 space-y-2 sm:space-y-3">
                                <div class="flex items-center gap-2 px-2 sm:px-3 py-2 rounded-lg border border-[#2A2A2A] bg-[#121212]">
                                    <svg class="w-4 h-4 text-[#555555]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                    <input
                                        type="text"
                                        data-symbols-search
                                        placeholder="Поиск монет..."
                                        class="w-full bg-transparent border-none focus:ring-0 text-sm text-white placeholder:text-[#555555] outline-none font-medium"
                                    >
                                </div>
                                <div class="max-h-64 overflow-y-auto space-y-1 pr-1" data-symbols-options></div>
                            </div>
                            <select id="performance-symbols" data-symbols-select name="symbols[]" multiple class="hidden">
                                @foreach ($symbolOptions as $option)
                                    @php
                                        $optionBase = strtoupper((string) preg_replace('/USDT$/i', '', (string) $option));
                                        $optionIcon = $coinIconMap[$optionBase] ?? null;
                                    @endphp
                                    <option value="{{ $option }}" data-icon="{{ $optionIcon ?? '' }}" {{ in_array($option, $selectedSymbols, true) ? 'selected' : '' }}>
                                        {{ $option === '__ALL__' ? __('custom.performance.scope_portfolio') : $option }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="xl:col-span-5 2xl:col-span-4 space-y-5">
                        <label for="performance-capital" class="block text-sm font-mono font-semibold text-[#888888] uppercase tracking-widest">3. Капитал</label>
                        <div class="space-y-2.5" data-capital-picker>
                            <div class="relative group">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="h-7 min-w-7 px-2 rounded-md border border-[#00E676]/30 bg-[#00E676]/10 text-[#00E676] text-sm font-mono font-bold inline-flex items-center justify-center">$</span>
                                </div>
                                <input id="performance-capital" name="capital" type="number" min="100" max="1000000000" step="100" value="{{ rtrim(rtrim(number_format($initialCapital, 2, '.', ''), '0'), '.') }}" class="block w-full pl-12 pr-16 py-4 bg-[#121212] border border-[#2A2A2A] rounded-xl text-lg text-white font-mono font-semibold focus:outline-none focus:border-[#00E676] focus:bg-[#1A1A1A] transition-all">
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="h-7 px-2 rounded-md border border-[#2A2A2A] bg-[#0A0A0A] text-[#777777] text-[11px] font-mono tracking-wide inline-flex items-center justify-center">USD</span>
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-2">
                                <button type="button" data-capital-option="1000" class="h-8 rounded-lg border border-[#2A2A2A] bg-[#121212] text-[#AAAAAA] text-[11px] font-mono hover:border-[#404040] hover:text-white transition-colors">$1k</button>
                                <button type="button" data-capital-option="10000" class="h-8 rounded-lg border border-[#00E676]/45 bg-[#00E676]/12 text-[#00E676] text-[11px] font-mono transition-colors">$10k</button>
                                <button type="button" data-capital-option="50000" class="h-8 rounded-lg border border-[#2A2A2A] bg-[#121212] text-[#AAAAAA] text-[11px] font-mono hover:border-[#404040] hover:text-white transition-colors">$50k</button>
                                <button type="button" data-capital-option="100000" class="h-8 rounded-lg border border-[#2A2A2A] bg-[#121212] text-[#AAAAAA] text-[11px] font-mono hover:border-[#404040] hover:text-white transition-colors">$100k</button>
                                <button type="button" data-capital-option="250000" class="h-8 rounded-lg border border-[#2A2A2A] bg-[#121212] text-[#AAAAAA] text-[11px] font-mono hover:border-[#404040] hover:text-white transition-colors">$250k</button>
                            </div>
                        </div>

                        <label for="performance-leverage" class="block text-sm font-mono font-semibold text-[#888888] uppercase tracking-widest">Плечо</label>
                        <div class="relative" data-leverage-picker>
                            <button
                                type="button"
                                class="w-full border border-[#2A2A2A] bg-[#121212] rounded-xl px-4 py-4 text-lg text-white font-mono flex items-center justify-between gap-3 hover:border-[#404040] transition-colors"
                                data-leverage-toggle
                                aria-expanded="false"
                            >
                                <span class="font-semibold tracking-wide" data-leverage-label>x{{ (int) $selectedLeverage }}</span>
                                <svg class="w-5 h-5 text-[#777777] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div class="hidden absolute z-30 left-0 right-0 mt-2 rounded-xl border border-[#2A2A2A] bg-[#0A0A0A]/95 p-3 shadow-[0_10px_40px_rgba(0,0,0,0.45)]" data-leverage-menu>
                                <div class="grid grid-cols-5 gap-2">
                                    @foreach ($leverageOptions as $option)
                                        <button
                                            type="button"
                                            data-leverage-option="{{ $option }}"
                                            class="{{ (int) $selectedLeverage === (int) $option ? 'border-[#00E676]/50 bg-[#00E676]/15 text-[#00E676]' : 'border-[#2A2A2A] bg-[#121212] text-[#BBBBBB] hover:border-[#404040] hover:text-white' }} h-9 rounded-lg border text-sm font-mono font-semibold transition-colors"
                                        >
                                            x{{ $option }}
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                            <select id="performance-leverage" name="leverage" class="hidden" data-leverage-select>
                                @foreach ($leverageOptions as $option)
                                    <option value="{{ $option }}" {{ (int) $selectedLeverage === (int) $option ? 'selected' : '' }}>x{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <label class="block text-sm font-mono font-semibold text-[#888888] uppercase tracking-widest">4. Период симуляции</label>
                    <div class="flex flex-col lg:flex-row gap-4 bg-[#121212] border border-[#2A2A2A] p-2 rounded-xl">
                        <div class="flex p-1 bg-[#0A0A0A] rounded-lg shrink-0 w-full lg:w-auto overflow-x-auto">
                            @foreach ($periodOptions as $option)
                                @php
                                    $periodChip = match ((int) $option) {
                                        7 => '1W',
                                        14 => '2W',
                                        30 => '1M',
                                        60 => '2M',
                                        90 => '3M',
                                        180 => '6M',
                                        365 => '1Y',
                                        730 => '2Y',
                                        default => (string) $option,
                                    };
                                @endphp
                                <label class="{{ (int) $selectedPeriodDays === (int) $option ? 'flex-1 lg:w-16 py-2.5 text-sm font-mono font-bold rounded bg-[#1A1A1A] text-[#00E676] border border-[#2A2A2A] text-center cursor-pointer' : 'flex-1 lg:w-16 py-2.5 text-sm font-mono font-medium rounded text-[#555555] hover:text-white transition-colors text-center cursor-pointer' }}">
                                    <input type="radio" class="sr-only" name="period" value="{{ $option }}" {{ (int) $selectedPeriodDays === (int) $option ? 'checked' : '' }}>
                                    {{ $periodChip }}
                                </label>
                            @endforeach
                        </div>

                        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 flex-1 w-full">
                            <div class="relative flex-1 group w-full sm:w-auto">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-[#555555]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <input type="date" value="{{ $periodStartDate->format('Y-m-d') }}" class="block w-full pl-10 pr-4 py-3 bg-[#0A0A0A] border border-transparent rounded-lg text-sm text-white font-mono focus:outline-none focus:border-[#404040]" readonly>
                            </div>
                            <span class="text-[#555555] w-4 text-center">-</span>
                            <div class="relative flex-1 group w-full sm:w-auto">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="w-4 h-4 text-[#555555]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                </div>
                                <input type="date" value="{{ $periodEndDate->format('Y-m-d') }}" class="block w-full pl-10 pr-4 py-3 bg-[#0A0A0A] border border-transparent rounded-lg text-sm text-white font-mono focus:outline-none focus:border-[#404040]" readonly>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-12 relative">
                    <div class="absolute inset-0 bg-[#00E676]/5 rounded-2xl blur-xl"></div>
                    <div class="relative bg-[#1A1A1A] border border-[#404040] rounded-2xl p-6 flex flex-col xl:flex-row items-start sm:items-center justify-between gap-6 shadow-2xl">
                        <div class="flex flex-wrap items-center gap-x-8 gap-y-5 w-full xl:w-auto">
                            <div>
                                <p class="text-xs text-[#555555] uppercase tracking-wider mb-1 font-mono">Выбранный сетап</p>
                                <p class="text-white font-semibold text-xl">{{ $selectedPresetLabel }}</p>
                            </div>
                            <div class="h-10 w-px bg-[#2A2A2A] hidden sm:block"></div>
                            <div>
                                <p class="text-xs text-[#555555] uppercase tracking-wider mb-1 font-mono">Плечо</p>
                                <p class="text-[#00E676] font-mono font-bold text-xl drop-shadow-[0_0_8px_rgba(0,230,118,0.5)]">x{{ rtrim(rtrim(number_format($selectedLeverage, 2, '.', ''), '0'), '.') }}</p>
                            </div>
                            <div class="h-10 w-px bg-[#2A2A2A] hidden sm:block"></div>
                            <div>
                                <p class="text-xs text-[#555555] uppercase tracking-wider mb-1 font-mono">Выбрано монет</p>
                                <p class="text-white font-semibold text-xl">{{ $selectedScope === 'portfolio' ? __('custom.performance.scope_portfolio') : ($selectedSymbolsCount . ' активы') }}</p>
                            </div>
                            <div class="h-10 w-px bg-[#2A2A2A] hidden lg:block"></div>
                            <div>
                                <p class="text-xs text-[#555555] uppercase tracking-wider mb-1 font-mono">Комиссия биржи</p>
                                <p class="text-[#888888] font-mono text-base">{{ number_format($commissionPct, 4, '.', '') }}% / стор.</p>
                            </div>
                        </div>

                        <button type="submit" class="w-full xl:w-auto shrink-0 bg-[#00E676] hover:bg-[#00FF88] text-[#0A0A0A] font-bold py-4 px-10 rounded-xl shadow-[0_0_20px_rgba(0,230,118,0.3)] hover:shadow-[0_0_30px_rgba(0,230,118,0.5)] transition-all transform hover:-translate-y-0.5 active:translate-y-0 flex items-center justify-center gap-3 text-base">
                            <span>ЗАПУСТИТЬ ТЕСТ</span>
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 6.82v10.36a1 1 0 001.52.85l8.07-5.18a1 1 0 000-1.68L9.52 5.97A1 1 0 008 6.82z"></path></svg>
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <div class="glass-panel p-6 relative group overflow-hidden">
                <div class="text-gray-400 font-mono text-xs uppercase tracking-widest mb-2">{{ __('custom.performance.total_signals') }}</div>
                <div class="text-4xl font-black italic text-white group-hover:text-[#00ff88] transition-colors">{{ number_format($totalTrades, 0, '.', ' ') }}</div>
                <div class="mt-4 flex items-center gap-2 text-xs font-mono text-gray-500">{{ __('custom.performance.wins_losses', ['wins' => $wins, 'losses' => $losses]) }}</div>
            </div>

            <div class="glass-panel p-6 relative group">
                <div class="absolute inset-0 bg-[#00ff88]/5 opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                <div class="relative z-10">
                    <div class="text-gray-400 font-mono text-xs uppercase tracking-widest mb-2">{{ __('custom.performance.profitable') }}</div>
                    <div class="text-4xl font-black italic text-[#00ff88] drop-shadow-[0_0_8px_rgba(0,255,136,0.3)]">{{ $winRatePct !== null ? number_format($winRatePct, 1) . '%' : '—' }}</div>
                    <div class="w-full h-1 bg-gray-800 mt-4 rounded-full overflow-hidden">
                        <div class="h-full bg-[#00ff88] shadow-[0_0_10px_#00ff88]" style="width: {{ $winRatePct !== null ? max(0, min(100, $winRatePct)) : 0 }}%"></div>
                    </div>
                </div>
            </div>

            <div class="glass-panel p-6 relative group">
                <div class="text-gray-400 font-mono text-xs uppercase tracking-widest mb-2">{{ __('custom.performance.avg_roi') }}</div>
                <div class="text-4xl font-black italic {{ ($avgTradePct ?? 0) >= 0 ? 'text-[#00ff88]' : 'text-[#ff3b3b]' }} flex items-center gap-2">
                    {{ $avgTradePct !== null ? ($avgTradePct > 0 ? '+' : '') . number_format($avgTradePct, 1) . '%' : '—' }}
                </div>
                <div class="mt-4 font-mono text-xs text-gray-500">PER TRADE AVERAGE</div>
            </div>

            <div class="glass-panel p-6 relative group border-[#ff3b3b]/20 hover:border-[#ff3b3b]/50">
                <div class="relative z-10">
                    <div class="text-gray-400 font-mono text-xs uppercase tracking-widest mb-2">{{ __('custom.performance.max_drawdown') }}</div>
                    <div class="text-4xl font-black italic text-[#ff3b3b] drop-shadow-[0_0_8px_rgba(255,59,59,0.3)]">{{ $maxDrawdownPct !== null ? '-' . number_format(abs($maxDrawdownPct), 1) . '%' : '—' }}</div>
                    <div class="mt-4 font-mono text-xs text-[#ff3b3b]/70 uppercase">{{ __('custom.performance.risk_level', ['level' => ($maxDrawdownPct !== null && $maxDrawdownPct < 10) ? __('custom.performance.risk_low') : __('custom.performance.risk_medium')]) }}</div>
                    <div class="mt-1 font-mono text-[11px] text-gray-400">
                        {{ $drawdownPeakAmount !== null && $drawdownTroughAmount !== null ? ('$' . number_format($drawdownPeakAmount, 2, '.', ' ') . ' → $' . number_format($drawdownTroughAmount, 2, '.', ' ')) : '—' }}
                    </div>
                </div>
            </div>

        </div>

        <div class="glass-panel p-5 text-sm text-gray-400 space-y-3">
            <div class="flex flex-wrap gap-x-8 gap-y-2 font-mono">
                <span>{{ __('custom.performance.net_expectancy') }}: <strong class="text-white">{{ $avgR !== null ? number_format($avgR, 3) . ' R' : '—' }}</strong></span>
                <span>{{ __('custom.performance.profit_factor') }}: <strong class="text-white">{{ $profitFactor !== null ? number_format($profitFactor, 2) : '—' }}</strong></span>
                <span>{{ __('custom.performance.risk_skipped') }}: {{ $simulation['skipped_risk'] ?? 0 }}</span>
            </div>
            <p>{{ __('custom.performance.portfolio_policy', config('trading.portfolio_risk')) }}</p>
            <p>{{ __('custom.performance.drawdown_limitations', config('trading.portfolio_risk')) }}</p>
            @if ($simulation['risk_halted'] ?? false)
                <p class="text-[#ff3b3b]">{{ __('custom.performance.risk_halted') }}</p>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-6">
            <div
                class="glass-panel p-4 md:p-8 flex min-h-0 flex-col h-[440px] md:h-[620px] overflow-hidden"
                data-performance-equity-root="true"
                data-equity-series='@json($metrics["equity_curve"] ?? [])'
                data-equity-period-days="{{ $periodDays }}"
                data-equity-period-end="{{ ($snapshot?->period_end ?? now())->toIso8601String() }}"
            >
                <div class="mb-2 md:mb-3 text-xs md:text-base font-mono text-gray-300">
                    {{ __('custom.performance.chart_capital_change_caption') }}
                </div>
                <div class="min-h-0 flex-1 flex flex-col gap-3">
                    <div class="h-[320px] md:h-[520px] w-full relative border border-white/10 p-1.5 md:p-2 overflow-hidden rounded-xl" data-lwc-equity-container>
                        <div class="equity-logo-watermark" aria-hidden="true"></div>
                        <div class="absolute inset-0 flex items-center justify-center text-xs font-mono text-gray-500" data-lwc-loading>{{ __('custom.performance.chart_loading') }}</div>
                    </div>
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-1 text-[11px] font-mono text-gray-400 px-1">
                        <div data-lwc-change class="text-xs md:text-base font-semibold">—</div>
                        <div data-lwc-last-value class="text-sm md:text-lg font-bold text-white">—</div>
                    </div>
                </div>
            </div>
        </div>

        <section class="glass-panel p-8 overflow-hidden">
            <h3 class="text-xl font-bold italic text-white mb-8 uppercase">{!! __('custom.performance.distribution') !!}</h3>

            <div class="grid grid-cols-1 xl:grid-cols-[360px,1fr] gap-8 items-center">
                <div class="flex flex-col items-center justify-center">
                    <div class="relative w-56 h-56">
                        <div class="w-full h-full conic-chart relative rounded-full" style="background: conic-gradient(#00ff88 0% {{ number_format($longPct, 2, '.', '') }}%, transparent {{ number_format($longPct, 2, '.', '') }}% {{ number_format(min(100, $longPct + 3), 2, '.', '') }}%, #ff3b3b {{ number_format(min(100, $longPct + 3), 2, '.', '') }}% 100%);"></div>
                        <div class="absolute inset-3 bg-[#0a0a0a] rounded-full flex items-center justify-center flex-col z-10">
                            <span class="text-4xl font-black italic text-white">{{ number_format($longPct, 0) }}%</span>
                            <span class="text-sm font-mono text-[#00ff88] uppercase tracking-wider">LONG</span>
                        </div>
                    </div>

                    <div class="w-full max-w-[300px] grid grid-cols-2 gap-4 mt-8">
                        <div class="flex items-center gap-3 rounded-lg border border-[#00ff88]/20 bg-[#00ff88]/5 px-3 py-2">
                            <div class="w-3 h-3 bg-[#00ff88] shadow-[0_0_8px_#00ff88]"></div>
                            <div><div class="text-xs font-mono text-gray-500">LONG</div><div class="text-base font-bold text-white">{{ number_format($longPct, 1) }}%</div></div>
                        </div>
                        <div class="flex items-center gap-3 rounded-lg border border-[#ff3b3b]/20 bg-[#ff3b3b]/5 px-3 py-2">
                            <div class="w-3 h-3 bg-[#ff3b3b] shadow-[0_0_8px_#ff3b3b]"></div>
                            <div><div class="text-xs font-mono text-gray-500">SHORT</div><div class="text-base font-bold text-white">{{ number_format($shortPct, 1) }}%</div></div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="rounded-xl border border-white/10 bg-black/20 px-5 py-4">
                        <div class="text-[11px] font-mono uppercase tracking-wider text-gray-500">LONG SIGNALS</div>
                        <div class="mt-2 text-3xl font-black italic text-[#00ff88]">{{ number_format($longCount, 0, '.', ' ') }}</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 px-5 py-4">
                        <div class="text-[11px] font-mono uppercase tracking-wider text-gray-500">SHORT SIGNALS</div>
                        <div class="mt-2 text-3xl font-black italic text-[#ff3b3b]">{{ number_format($shortCount, 0, '.', ' ') }}</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 px-5 py-4">
                        <div class="text-[11px] font-mono uppercase tracking-wider text-gray-500">L/S RATIO</div>
                        <div class="mt-2 text-3xl font-black italic text-white">{{ $shortCount > 0 ? number_format($longCount / $shortCount, 2, '.', '') : '∞' }}</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 px-5 py-4 md:col-span-2">
                        <div class="text-[11px] font-mono uppercase tracking-wider text-gray-500">SCOPE</div>
                        <div class="mt-2 text-xl font-black italic text-white">{{ $selectedScopeLabel }}</div>
                    </div>
                    <div class="rounded-xl border border-white/10 bg-black/20 px-5 py-4">
                        <div class="text-[11px] font-mono uppercase tracking-wider text-gray-500">PERIOD</div>
                        <div class="mt-2 text-3xl font-black italic text-white">{{ $selectedPeriodLabel }}</div>
                    </div>
                </div>
            </div>
        </section>

        @include('livewire.partials.performance-history')
    </main>

    <button
        type="button"
        data-scroll-top
        class="fixed bottom-6 right-6 z-50 hidden h-11 w-11 items-center justify-center rounded-full border border-[#00ff88]/50 bg-black/80 text-[#00ff88] shadow-[0_0_18px_rgba(0,255,136,0.25)] transition hover:bg-[#00ff88]/10"
        aria-label="Scroll to top"
    >
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 15l7-7 7 7"></path>
        </svg>
    </button>

    <div class="fixed bottom-0 left-0 w-full h-1 bg-gradient-to-r from-black via-[#00ff88] to-black opacity-30 pointer-events-none"></div>
</div>

<script>
(() => {
    const historyLoadMoreText = @json(__('custom.performance.show_more'));
    const historyLoadingText = @json(__('custom.performance.loading'));

    const state = new WeakMap();
    const observers = new WeakMap();

    const numberFormatter = new Intl.NumberFormat(undefined, { maximumFractionDigits: 2 });
    const shortDateFormatter = new Intl.DateTimeFormat(undefined, { timeZone: document.documentElement.dataset.displayTimezone || undefined, day: '2-digit', month: '2-digit', year: '2-digit' });
    const fullDateFormatter = new Intl.DateTimeFormat(undefined, {
        timeZone: document.documentElement.dataset.displayTimezone || undefined,
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });

    const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
    const pickColorByMode = (mode) => mode === 'drawdown' ? '#ff3b3b' : '#00ff88';
    const keyPointLabel = {
        start: 'START',
        end: 'END',
        max: 'HIGH',
        min: 'LOW',
        dd: 'MAX DD',
    };

    const makeTimeline = (canvas, length) => {
        const periodDays = Math.max(1, Number(canvas.getAttribute('data-equity-period-days') || 365));
        const endIso = canvas.getAttribute('data-equity-period-end');
        const endDate = endIso ? new Date(endIso) : new Date();
        const endTs = Number.isFinite(endDate.getTime()) ? endDate.getTime() : Date.now();
        const startTs = endTs - (periodDays * 24 * 60 * 60 * 1000);
        if (length <= 1) {
            return [startTs];
        }
        const step = (endTs - startTs) / (length - 1);
        return Array.from({ length }, (_, idx) => startTs + (step * idx));
    };

    const ensureViewport = (canvas, values) => {
        const existing = state.get(canvas);
        if (existing && existing.fullLength === values.length && existing.viewport) {
            return existing.viewport;
        }
        return { start: 0, end: values.length - 1 };
    };

    const buildVisible = (values, timeline, viewport) => {
        const start = clamp(Math.floor(viewport.start), 0, values.length - 2);
        const end = clamp(Math.ceil(viewport.end), start + 1, values.length - 1);
        const sliceValues = values.slice(start, end + 1);
        const sliceTimeline = timeline.slice(start, end + 1);
        return { start, end, values: sliceValues, timeline: sliceTimeline };
    };

    const getCachedBase = (canvas, existing) => {
        const raw = canvas.getAttribute('data-equity-series') || '';
        if (existing && existing.rawSeries === raw && Array.isArray(existing.baseValues) && existing.baseValues.length > 0) {
            return { raw, values: existing.baseValues };
        }

        try {
            const values = JSON.parse(raw).map((v) => Number(v)).filter((v) => Number.isFinite(v));
            return { raw, values };
        } catch (e) {
            return { raw, values: [] };
        }
    };

    const formatMoneyCompact = (value) => {
        const abs = Math.abs(value);
        if (abs >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(2)}B`;
        if (abs >= 1_000_000) return `${(value / 1_000_000).toFixed(2)}M`;
        if (abs >= 1_000) return `${(value / 1_000).toFixed(1)}K`;
        return numberFormatter.format(value);
    };

    const buildSeriesByMode = (values, timeline, periodDays, mode) => {
        if (mode === 'drawdown') {
            let peak = values[0] || 0;
            const dd = values.map((value) => {
                peak = Math.max(peak, value);
                if (peak <= 0) return 0;
                return ((value - peak) / peak) * 100;
            });
            return { values: dd, timeline };
        }

        if (mode === 'monthly') {
            const stepDays = Math.max(1, periodDays / Math.max(1, values.length - 1));
            const step = Math.max(1, Math.round(30 / stepDays));
            const monthly = values.map((value, index) => {
                if (index < step) return 0;
                const prev = values[index - step];
                if (!Number.isFinite(prev) || prev === 0) return 0;
                return ((value - prev) / prev) * 100;
            });
            return { values: monthly, timeline };
        }

        return { values, timeline };
    };

    const hideChartLoading = (canvas) => {
        const loader = canvas.parentElement?.querySelector('[data-equity-loading]');
        if (!loader) return;
        loader.classList.add('equity-loading-hidden');
    };

    const pickKeyIndexes = (series, mode) => {
        if (!Array.isArray(series) || series.length < 2) return [];
        let maxIdx = 0;
        let minIdx = 0;
        for (let i = 1; i < series.length; i++) {
            if (series[i] > series[maxIdx]) maxIdx = i;
            if (series[i] < series[minIdx]) minIdx = i;
        }
        const items = [
            { idx: 0, type: 'start' },
            { idx: series.length - 1, type: 'end' },
            { idx: maxIdx, type: 'max' },
            { idx: minIdx, type: mode === 'drawdown' ? 'dd' : 'min' },
        ];
        const unique = new Map();
        items.forEach((item) => {
            if (!unique.has(item.idx)) unique.set(item.idx, item);
        });
        return Array.from(unique.values());
    };

    const render = (canvas, hoverIndex = null) => {
        const existing = state.get(canvas);
        const baseCache = getCachedBase(canvas, existing);
        const baseValues = baseCache.values;
        if (baseValues.length < 2) return;
        const timelineKey = `${canvas.getAttribute('data-equity-period-days') || ''}|${canvas.getAttribute('data-equity-period-end') || ''}|${baseValues.length}`;
        const baseTimeline = existing && existing.timelineKey === timelineKey && Array.isArray(existing.baseTimeline)
            ? existing.baseTimeline
            : makeTimeline(canvas, baseValues.length);
        const mode = (canvas.getAttribute('data-equity-view') || 'equity').toLowerCase();
        const periodDays = Math.max(1, Number(canvas.getAttribute('data-equity-period-days') || 365));
        const built = buildSeriesByMode(baseValues, baseTimeline, periodDays, mode);
        const values = built.values;
        const timeline = built.timeline;

        const viewport = ensureViewport(canvas, values);
        const visible = buildVisible(values, timeline, viewport);
        if (visible.values.length < 2) return false;

        const dpr = Math.max(1, window.devicePixelRatio || 1);
        const rect = canvas.getBoundingClientRect();
        const width = Math.floor(rect.width);
        const height = Math.floor(rect.height);
        if (width < 40 || height < 40) return false;
        canvas.width = Math.floor(width * dpr);
        canvas.height = Math.floor(height * dpr);
        const ctx = canvas.getContext('2d');
        if (!ctx) return false;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        ctx.clearRect(0, 0, width, height);

        const pad = { t: 24, r: 18, b: 30, l: 56 };
        const w = width - pad.l - pad.r;
        const h = height - pad.t - pad.b;
        const min = Math.min(...visible.values);
        const max = Math.max(...visible.values);
        const range = Math.max(1e-9, max - min);
        const baselineColor = pickColorByMode(mode);
        const isUp = visible.values[visible.values.length - 1] >= visible.values[0];
        const lineColor = mode === 'equity' ? (isUp ? '#00ff88' : '#ff3b3b') : baselineColor;
        const glowColor = isUp ? 'rgba(0,255,136,0.55)' : 'rgba(255,59,59,0.5)';

        ctx.strokeStyle = 'rgba(255,255,255,0.08)';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const y = pad.t + (h * i / 5);
            ctx.beginPath();
            ctx.moveTo(pad.l, y);
            ctx.lineTo(width - pad.r, y);
            ctx.stroke();
        }

        const points = visible.values.map((v, i) => {
            const x = pad.l + (i / (visible.values.length - 1)) * w;
            const ratio = (v - min) / range;
            const y = pad.t + (1 - ratio) * h;
            return { x, y, v, ts: visible.timeline[i], globalIndex: visible.start + i };
        });

        const grad = ctx.createLinearGradient(0, pad.t, 0, height - pad.b);
        grad.addColorStop(0, isUp ? 'rgba(0,255,136,0.26)' : 'rgba(255,59,59,0.23)');
        grad.addColorStop(1, 'rgba(0,0,0,0)');
        ctx.beginPath();
        ctx.moveTo(points[0].x, height - pad.b);
        points.forEach((p) => ctx.lineTo(p.x, p.y));
        ctx.lineTo(points[points.length - 1].x, height - pad.b);
        ctx.closePath();
        ctx.fillStyle = grad;
        ctx.fill();

        ctx.strokeStyle = lineColor;
        ctx.lineWidth = 2;
        ctx.shadowColor = glowColor;
        ctx.shadowBlur = 10;
        ctx.beginPath();
        points.forEach((p, i) => i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y));
        ctx.stroke();
        ctx.shadowBlur = 0;

        const last = points[points.length - 1];
        ctx.fillStyle = lineColor;
        ctx.beginPath();
        ctx.arc(last.x, last.y, 3.2, 0, Math.PI * 2);
        ctx.fill();

        const keyIndexes = pickKeyIndexes(visible.values, mode);
        keyIndexes.forEach((keyPoint) => {
            const p = points[keyPoint.idx];
            if (!p) return;
            const isEnd = keyPoint.type === 'end';
            const keyColor = keyPoint.type === 'dd' || (keyPoint.type === 'min' && mode !== 'equity')
                ? '#ff3b3b'
                : (keyPoint.type === 'max' ? '#00ff88' : '#e5e7eb');
            ctx.save();
            ctx.fillStyle = keyColor;
            ctx.strokeStyle = 'rgba(0,0,0,0.8)';
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.arc(p.x, p.y, isEnd ? 4.2 : 3.5, 0, Math.PI * 2);
            ctx.fill();
            ctx.stroke();
            ctx.restore();
        });

        ctx.font = '11px ui-monospace, SFMono-Regular, Menlo, monospace';
        ctx.fillStyle = 'rgba(156, 163, 175, 0.85)';
        const formatAxisValue = (value) => {
            if (mode === 'equity') {
                return formatMoneyCompact(value);
            }
            return `${value.toFixed(1)}%`;
        };
        for (let i = 0; i <= 5; i++) {
            const ratio = i / 5;
            const y = pad.t + (h * ratio);
            const val = max - (range * ratio);
            const text = formatAxisValue(val);
            const textWidth = ctx.measureText(text).width;
            ctx.fillText(text, Math.max(4, pad.l - textWidth - 8), y + 3);
        }

        const firstDate = shortDateFormatter.format(new Date(visible.timeline[0]));
        const midDate = shortDateFormatter.format(new Date(visible.timeline[Math.floor(visible.timeline.length / 2)]));
        const lastDate = shortDateFormatter.format(new Date(visible.timeline[visible.timeline.length - 1]));
        ctx.fillStyle = 'rgba(156, 163, 175, 0.85)';
        ctx.fillText(firstDate, pad.l, height - 8);
        const midWidth = ctx.measureText(midDate).width;
        ctx.fillText(midDate, pad.l + (w / 2) - (midWidth / 2), height - 8);
        const lastWidth = ctx.measureText(lastDate).width;
        ctx.fillText(lastDate, width - pad.r - lastWidth, height - 8);

        const lastText = mode === 'equity'
            ? `LAST ${formatMoneyCompact(last.v)}`
            : `LAST ${last.v.toFixed(2)}%`;
        const lastTextWidth = ctx.measureText(lastText).width;
        ctx.fillStyle = lineColor;
        ctx.fillText(lastText, width - pad.r - lastTextWidth, pad.t + 10);

        keyIndexes.forEach((keyPoint) => {
            const p = points[keyPoint.idx];
            if (!p) return;
            const label = keyPointLabel[keyPoint.type] || '';
            if (!label) return;
            const fontSize = 9;
            ctx.save();
            ctx.font = `${fontSize}px ui-monospace, SFMono-Regular, Menlo, monospace`;
            const tw = ctx.measureText(label).width;
            const labelW = tw + 8;
            const labelH = 14;
            const labelX = clamp(p.x - (labelW / 2), pad.l, width - pad.r - labelW);
            const defaultY = p.y - 20;
            const labelY = defaultY <= pad.t + 2 ? (p.y + 10) : defaultY;
            ctx.fillStyle = 'rgba(12, 12, 12, 0.92)';
            ctx.strokeStyle = 'rgba(255, 255, 255, 0.16)';
            ctx.lineWidth = 1;
            if (typeof ctx.roundRect === 'function') {
                ctx.beginPath();
                ctx.roundRect(labelX, labelY, labelW, labelH, 5);
            } else {
                ctx.beginPath();
                ctx.rect(labelX, labelY, labelW, labelH);
            }
            ctx.fill();
            ctx.stroke();
            ctx.fillStyle = 'rgba(229, 231, 235, 0.95)';
            ctx.fillText(label, labelX + 4, labelY + 10);
            ctx.restore();
        });

        if (hoverIndex !== null && points[hoverIndex]) {
            const hp = points[hoverIndex];
            ctx.save();
            ctx.strokeStyle = 'rgba(255,255,255,0.18)';
            ctx.setLineDash([5, 4]);
            ctx.beginPath();
            ctx.moveTo(hp.x, pad.t);
            ctx.lineTo(hp.x, height - pad.b);
            ctx.stroke();
            ctx.setLineDash([]);
            ctx.fillStyle = lineColor;
            ctx.beginPath();
            ctx.arc(hp.x, hp.y, 4, 0, Math.PI * 2);
            ctx.fill();
            ctx.restore();
        }

        state.set(canvas, {
            rawSeries: baseCache.raw,
            baseValues,
            baseTimeline,
            timelineKey,
            points,
            values,
            timeline,
            visible,
            width,
            height,
            pad,
            lineColor,
            viewport: { start: visible.start, end: visible.end },
            fullLength: values.length,
            mode,
            isDragging: existing?.isDragging ?? false,
            dragStartX: existing?.dragStartX ?? 0,
            dragStartViewport: existing?.dragStartViewport ?? null,
            lastHoverIndex: existing?.lastHoverIndex ?? null,
        });
        hideChartLoading(canvas);
        return true;
    };

    const hideTooltip = (canvas) => {
        const tooltip = canvas.parentElement?.querySelector('[data-equity-tooltip="true"]');
        if (!tooltip) return;
        tooltip.classList.add('equity-tooltip-hidden');
    };

    const showTooltip = (canvas, index) => {
        const tooltip = canvas.parentElement?.querySelector('[data-equity-tooltip="true"]');
        const meta = state.get(canvas);
        if (!tooltip || !meta) return;
        const p = meta.points[index];
        if (!p) return;

        const start = meta.values[0];
        const changePct = start !== 0 ? ((p.v - start) / start) * 100 : 0;
        const step = p.globalIndex + 1;
        const mode = meta.mode || 'equity';

        const stepEl = tooltip.querySelector('[data-equity-tooltip-step]');
        const dateEl = tooltip.querySelector('[data-equity-tooltip-date]');
        const valueEl = tooltip.querySelector('[data-equity-tooltip-value]');
        const changeEl = tooltip.querySelector('[data-equity-tooltip-change]');
        if (stepEl) stepEl.textContent = `STEP ${step}/${meta.values.length}`;
        if (dateEl) dateEl.textContent = fullDateFormatter.format(new Date(p.ts));
        if (valueEl) {
            valueEl.textContent = mode === 'equity'
                ? numberFormatter.format(p.v)
                : `${p.v.toFixed(2)}%`;
        }
        if (changeEl) {
            if (mode === 'equity') {
                const sign = changePct > 0 ? '+' : '';
                changeEl.textContent = `${sign}${changePct.toFixed(2)}%`;
                changeEl.style.color = changePct >= 0 ? '#00ff88' : '#ff3b3b';
            } else {
                const sign = p.v > 0 ? '+' : '';
                changeEl.textContent = `${sign}${p.v.toFixed(2)}%`;
                changeEl.style.color = p.v >= 0 ? '#00ff88' : '#ff3b3b';
            }
        }

        tooltip.style.left = `${p.x}px`;
        tooltip.style.top = `${p.y}px`;
        tooltip.classList.remove('equity-tooltip-hidden');
    };

    const bind = (canvas) => {
        if (canvas.dataset.boundEquityCanvas === 'true') return;
        canvas.dataset.boundEquityCanvas = 'true';

        canvas.addEventListener('mousemove', (event) => {
            const meta = state.get(canvas);
            if (!meta) return;
            if (meta.isDragging) return;
            const rect = canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            let nearest = 0;
            let dist = Number.POSITIVE_INFINITY;
            meta.points.forEach((p, i) => {
                const d = Math.abs(p.x - x);
                if (d < dist) {
                    dist = d;
                    nearest = i;
                }
            });
            if (meta.lastHoverIndex === nearest) return;
            meta.lastHoverIndex = nearest;
            state.set(canvas, meta);
            render(canvas, nearest);
            showTooltip(canvas, nearest);
        });

        canvas.addEventListener('mouseleave', () => {
            const meta = state.get(canvas);
            if (meta) {
                meta.isDragging = false;
                meta.lastHoverIndex = null;
                state.set(canvas, meta);
            }
            render(canvas, null);
            hideTooltip(canvas);
        });

        canvas.addEventListener('wheel', (event) => {
            event.preventDefault();
            const meta = state.get(canvas);
            if (!meta || !meta.viewport) return;

            const rect = canvas.getBoundingClientRect();
            const x = event.clientX - rect.left;
            const ratio = clamp((x - meta.pad.l) / Math.max(1, rect.width - meta.pad.l - meta.pad.r), 0, 1);
            const span = meta.viewport.end - meta.viewport.start;
            const anchor = meta.viewport.start + (span * ratio);
            const zoomFactor = event.deltaY < 0 ? 0.85 : 1.18;
            let nextSpan = clamp(span * zoomFactor, 20, Math.max(20, meta.values.length - 1));
            if (nextSpan >= meta.values.length - 1) {
                meta.viewport = { start: 0, end: meta.values.length - 1 };
            } else {
                let nextStart = anchor - (nextSpan * ratio);
                let nextEnd = nextStart + nextSpan;
                if (nextStart < 0) {
                    nextStart = 0;
                    nextEnd = nextSpan;
                }
                if (nextEnd > meta.values.length - 1) {
                    nextEnd = meta.values.length - 1;
                    nextStart = nextEnd - nextSpan;
                }
                meta.viewport = { start: nextStart, end: nextEnd };
            }
            state.set(canvas, meta);
            render(canvas, null);
        }, { passive: false });

        canvas.addEventListener('mousedown', (event) => {
            const meta = state.get(canvas);
            if (!meta || !meta.viewport) return;
            meta.isDragging = true;
            meta.dragStartX = event.clientX;
            meta.dragStartViewport = { ...meta.viewport };
            state.set(canvas, meta);
            hideTooltip(canvas);
        });

        const stopDrag = () => {
            const meta = state.get(canvas);
            if (!meta) return;
            meta.isDragging = false;
            state.set(canvas, meta);
        };

        canvas.addEventListener('mouseup', stopDrag);
        canvas.addEventListener('mouseout', stopDrag);

        canvas.addEventListener('dblclick', () => {
            const meta = state.get(canvas);
            if (!meta) return;
            meta.viewport = { start: 0, end: meta.values.length - 1 };
            state.set(canvas, meta);
            render(canvas, null);
            hideTooltip(canvas);
        });

        canvas.addEventListener('mousemove', (event) => {
            const meta = state.get(canvas);
            if (!meta || !meta.isDragging || !meta.dragStartViewport) return;
            const pixels = event.clientX - meta.dragStartX;
            const drawable = Math.max(1, meta.width - meta.pad.l - meta.pad.r);
            const span = meta.dragStartViewport.end - meta.dragStartViewport.start;
            const pointsPerPixel = span / drawable;
            const deltaPoints = pixels * pointsPerPixel;

            let nextStart = meta.dragStartViewport.start - deltaPoints;
            let nextEnd = meta.dragStartViewport.end - deltaPoints;
            if (nextStart < 0) {
                nextEnd -= nextStart;
                nextStart = 0;
            }
            const maxEnd = meta.values.length - 1;
            if (nextEnd > maxEnd) {
                const overflow = nextEnd - maxEnd;
                nextStart -= overflow;
                nextEnd = maxEnd;
            }
            meta.viewport = { start: nextStart, end: nextEnd };
            state.set(canvas, meta);
            render(canvas, null);
        });

        if ('ResizeObserver' in window && !observers.has(canvas)) {
            const target = canvas.parentElement || canvas;
            const observer = new ResizeObserver(() => {
                render(canvas, null);
            });
            observer.observe(target);
            observers.set(canvas, observer);
        }
    };

    const drawAll = () => {
        let drewAtLeastOne = false;
        document.querySelectorAll('[data-equity-canvas="true"]').forEach((canvas) => {
            bind(canvas);
            drewAtLeastOne = render(canvas, null) || drewAtLeastOne;
        });
        return drewAtLeastOne;
    };

    const setViewportByRange = (canvas, rangeValue) => {
        const meta = state.get(canvas);
        if (!meta || !meta.values || meta.values.length < 2) return;
        if (rangeValue === 'all') {
            meta.viewport = { start: 0, end: meta.values.length - 1 };
            state.set(canvas, meta);
            render(canvas, null);
            return;
        }

        const periodDays = Math.max(1, Number(canvas.getAttribute('data-equity-period-days') || 365));
        const days = Number(rangeValue);
        if (!Number.isFinite(days) || days <= 0) return;
        const points = Math.max(2, Math.round((days / periodDays) * (meta.values.length - 1)));
        const end = meta.values.length - 1;
        const start = Math.max(0, end - points);
        meta.viewport = { start, end };
        state.set(canvas, meta);
        render(canvas, null);
    };

    const syncToggleButtons = (buttons, activeValue) => {
        buttons.forEach((button) => {
            if (button.classList.contains('hidden')) return;
            const value = button.getAttribute('data-equity-view') || button.getAttribute('data-equity-range');
            button.dataset.active = value === activeValue ? 'true' : 'false';
        });
    };

    const setupRangeButtons = (canvas, buttons) => {
        const periodDays = Math.max(1, Number(canvas.getAttribute('data-equity-period-days') || 365));
        let selected = canvas.getAttribute('data-equity-range') || 'all';
        const available = [];

        buttons.forEach((button) => {
            const value = button.getAttribute('data-equity-range') || 'all';
            if (value === 'all') {
                button.classList.remove('hidden');
                available.push(value);
                return;
            }
            const requiredDays = Number(button.getAttribute('data-equity-range-days') || value);
            const isAvailable = Number.isFinite(requiredDays) && requiredDays <= periodDays;
            button.classList.toggle('hidden', !isAvailable);
            if (isAvailable) available.push(value);
        });

        if (!available.includes(selected)) {
            const ordered = available
                .filter((item) => item !== 'all')
                .map((item) => Number(item))
                .filter((item) => Number.isFinite(item))
                .sort((a, b) => b - a);
            selected = ordered.length > 0 ? String(ordered[0]) : 'all';
            canvas.setAttribute('data-equity-range', selected);
        }
        return selected;
    };

    const initEquityControls = () => {
        document.querySelectorAll('[data-equity-canvas="true"]').forEach((canvas) => {
            const panel = canvas.closest('.glass-panel');
            if (!panel || panel.dataset.boundEquityControls === 'true') return;
            panel.dataset.boundEquityControls = 'true';

            const viewButtons = Array.from(panel.querySelectorAll('[data-equity-view]'));
            const rangeButtons = Array.from(panel.querySelectorAll('[data-equity-range]'));
            if (viewButtons.length === 0 && rangeButtons.length === 0) return;

            const applyView = (view) => {
                canvas.setAttribute('data-equity-view', view);
                syncToggleButtons(viewButtons, view);
                render(canvas, null);
            };

            const applyRange = (range) => {
                canvas.setAttribute('data-equity-range', range);
                syncToggleButtons(rangeButtons, range);
                setViewportByRange(canvas, range);
            };

            viewButtons.forEach((button) => {
                button.addEventListener('click', () => applyView(button.getAttribute('data-equity-view') || 'equity'));
            });
            rangeButtons.forEach((button) => {
                button.addEventListener('click', () => applyRange(button.getAttribute('data-equity-range') || 'all'));
            });

            applyView(canvas.getAttribute('data-equity-view') || 'equity');
            const initialRange = setupRangeButtons(canvas, rangeButtons);
            applyRange(initialRange || 'all');
        });
    };

    const scheduleDrawAll = () => {
        drawAll();
        requestAnimationFrame(() => drawAll());
        setTimeout(() => drawAll(), 120);
        setTimeout(() => drawAll(), 500);
    };

    const initScrollTopButton = () => {
        const button = document.querySelector('[data-scroll-top]');
        if (!button || button.dataset.boundScrollTop === 'true') return;
        button.dataset.boundScrollTop = 'true';

        const threshold = 900;
        const toggle = () => {
            const visible = window.scrollY > threshold;
            button.classList.toggle('hidden', !visible);
            button.classList.toggle('flex', visible);
        };

        button.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        window.addEventListener('scroll', toggle, { passive: true });
        toggle();
    };

    const initSymbolSelect = () => {
        document.querySelectorAll('[data-symbol-select]').forEach((selectRoot) => {
            if (selectRoot.dataset.boundSymbolSelect === 'true') return;
            selectRoot.dataset.boundSymbolSelect = 'true';

            const trigger = selectRoot.querySelector('[data-symbol-trigger]');
            const menu = selectRoot.querySelector('[data-symbol-menu]');
            const hidden = selectRoot.querySelector('[data-symbol-hidden]');
            const current = selectRoot.querySelector('[data-symbol-current-content]');
            const options = Array.from(selectRoot.querySelectorAll('[data-symbol-option]'));
            if (!trigger || !menu || !hidden || !current || options.length === 0) return;

            const close = () => {
                menu.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
            };

            const open = () => {
                menu.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
            };

            const syncActive = (value) => {
                options.forEach((option) => {
                    const isActive = option.dataset.value === value;
                    option.classList.toggle('bg-white/10', isActive);
                    const check = option.querySelector('[data-symbol-check]');
                    if (check) check.classList.toggle('opacity-0', !isActive);
                });
            };

            trigger.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (menu.classList.contains('hidden')) {
                    open();
                } else {
                    close();
                }
            });

            options.forEach((option) => {
                option.addEventListener('click', () => {
                    hidden.value = option.dataset.value || hidden.value;
                    const view = option.querySelector('[data-symbol-option-view]');
                    if (view) current.innerHTML = view.innerHTML;
                    syncActive(hidden.value);
                    close();
                });
            });

            document.addEventListener('click', (event) => {
                if (!selectRoot.contains(event.target)) {
                    close();
                }
            });
        });
    };

    const initPerformanceControls = () => {
        document.querySelectorAll('[data-performance-controls]').forEach((form) => {
            if (form.dataset.boundPerformanceControls === 'true') return;
            form.dataset.boundPerformanceControls = 'true';

            const picker = form.querySelector('[data-symbols-picker]');
            const symbolsSelect = form.querySelector('[data-symbols-select]');
            const selectAllButton = form.querySelector('[data-symbols-select-all]');
            const clearButton = form.querySelector('[data-symbols-clear]');
            const chipsContainer = form.querySelector('[data-symbols-chips]');
            const searchInput = form.querySelector('[data-symbols-search]');
            const dropdownToggle = form.querySelector('[data-symbols-dropdown-toggle]');
            const dropdownLabel = form.querySelector('[data-symbols-dropdown-label]');
            const dropdown = form.querySelector('[data-symbols-dropdown]');
            const optionsContainer = form.querySelector('[data-symbols-options]');
            if (!symbolsSelect || !picker) return;

            const allOptions = Array.from(symbolsSelect.options);
            const allOption = allOptions.find((option) => option.value === '__ALL__');
            const symbolOptions = allOptions.filter((option) => option.value !== '__ALL__');
            const symbolIconMap = {};
            const symbolBadgeMap = {
                BTC: { glyph: '₿', color: '#F7931A' },
                ETH: { glyph: 'Ξ', color: '#627EEA' },
                SOL: { glyph: 'S', color: '#14F195' },
                AVAX: { glyph: 'A', color: '#E84142' },
                BNB: { glyph: 'B', color: '#F0B90B' },
                XRP: { glyph: 'X', color: '#00AAE4' },
                TRX: { glyph: 'T', color: '#EF0027' },
                DOT: { glyph: 'D', color: '#E6007A' },
                ATOM: { glyph: 'A', color: '#5563FF' },
                LTC: { glyph: 'L', color: '#345D9D' },
                DOGE: { glyph: 'D', color: '#C2A633' },
                FIL: { glyph: 'F', color: '#17C1BC' },
                INJ: { glyph: 'I', color: '#00A3FF' },
                ICP: { glyph: 'I', color: '#29ABE2' },
                ALGO: { glyph: 'A', color: '#A2A2A2' },
                SUSHI: { glyph: 'S', color: '#FA52A0' },
                CHZ: { glyph: 'C', color: '#CD0124' },
                COMP: { glyph: 'C', color: '#00D395' },
                KAVA: { glyph: 'K', color: '#FF433E' },
                MANA: { glyph: 'M', color: '#FF2D55' },
                RUNE: { glyph: 'R', color: '#00A3FF' },
                XLM: { glyph: 'X', color: '#14B5D1' },
                ZRX: { glyph: 'Z', color: '#302C2C' },
                SEI: { glyph: 'S', color: '#FF4D4D' },
                SNX: { glyph: 'S', color: '#00D1FF' },
                SAND: { glyph: 'S', color: '#00ADEF' },
            };

            const normalizeBaseSymbol = (value) => String(value || '')
                .toUpperCase()
                .replace(/USDT$/, '')
                .replace(/[^A-Z0-9]/g, '');

            symbolOptions.forEach((option) => {
                const base = normalizeBaseSymbol(option.value);
                const icon = String(option.dataset.icon || '').trim();
                if (base && icon) {
                    symbolIconMap[base] = icon;
                }
            });

            const resolveSymbolBadge = (value) => {
                if (value === '__ALL__') {
                    return { glyph: '★', color: '#00E676' };
                }
                const base = normalizeBaseSymbol(value);
                const fallbackGlyph = base.slice(0, 1) || '?';
                const mapped = symbolBadgeMap[base];
                if (mapped) return mapped;
                return { glyph: fallbackGlyph, color: '#00E676' };
            };

            const resolveSymbolIcon = (value) => {
                const base = normalizeBaseSymbol(value);
                return symbolIconMap[base] || '';
            };

            const isDropdownOpen = () => dropdown && !dropdown.classList.contains('hidden');

            const openDropdown = () => {
                if (!dropdown || !dropdownToggle) return;
                dropdown.classList.remove('hidden');
                dropdownToggle.setAttribute('aria-expanded', 'true');
                if (searchInput) searchInput.focus();
            };

            const closeDropdown = () => {
                if (!dropdown || !dropdownToggle) return;
                dropdown.classList.add('hidden');
                dropdownToggle.setAttribute('aria-expanded', 'false');
            };

            const selectPortfolio = () => {
                allOptions.forEach((option) => {
                    option.selected = option.value === '__ALL__';
                });
            };

            const clearSelection = () => {
                allOptions.forEach((option) => {
                    option.selected = false;
                });
            };

            const normalizeSymbolsSelection = () => {
                const selectedValues = Array.from(symbolsSelect.selectedOptions).map((option) => option.value);
                const hasAll = selectedValues.includes('__ALL__');
                if (hasAll && selectedValues.length > 1) {
                    allOptions.forEach((option) => {
                        if (option.value !== '__ALL__') option.selected = false;
                    });
                }
            };

            const updateDropdownLabel = () => {
                if (!dropdownLabel) return;
                if (allOption && allOption.selected) {
                    dropdownLabel.textContent = 'Все монеты';
                    return;
                }
                const selected = symbolOptions.filter((option) => option.selected);
                if (selected.length === 0) {
                    dropdownLabel.textContent = 'Выберите монеты';
                    return;
                }
                if (selected.length === 1) {
                    dropdownLabel.textContent = selected[0].value;
                    return;
                }
                dropdownLabel.textContent = `Выбрано монет: ${selected.length}`;
            };

            const createChip = (label, value) => {
                const badge = resolveSymbolBadge(value);
                const icon = resolveSymbolIcon(value);
                const chip = document.createElement('div');
                chip.className = 'flex items-center gap-2 px-2 py-1.5 bg-bgDark border border-border rounded-lg group hover:border-danger/40 transition-colors cursor-pointer';
                chip.innerHTML = `
                    ${icon
                        ? `<img src="${icon}" alt="${label}" class="w-5 h-5 rounded-full object-cover">`
                        : `<div class="w-5 h-5 rounded-full flex items-center justify-center" style="background:${badge.color}20"><span class="text-[10px] font-bold" style="color:${badge.color}">${badge.glyph}</span></div>`
                    }
                    <span class="text-sm font-semibold tracking-wide text-white">${label}</span>
                    <div class="w-4 h-4 rounded-full flex items-center justify-center text-textMuted group-hover:text-danger group-hover:bg-danger/10 transition-colors ml-1" data-remove-symbol="${value}" role="button" aria-label="remove symbol">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </div>
                `;
                return chip;
            };

            const renderSymbolChips = () => {
                if (!chipsContainer) return;
                chipsContainer.innerHTML = '';
                const selected = symbolOptions.filter((option) => option.selected);
                if (allOption && allOption.selected) {
                    chipsContainer.appendChild(createChip('Все монеты', '__ALL__'));
                    return;
                }
                if (selected.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-xs font-mono text-[#555555] border border-dashed border-[#2A2A2A] rounded-lg';
                    empty.textContent = 'Нет выбранных монет';
                    chipsContainer.appendChild(empty);
                    return;
                }
                selected.forEach((option) => {
                    chipsContainer.appendChild(createChip(option.value, option.value));
                });
            };

            const createOptionRow = (value, label, isSelected) => {
                const badge = resolveSymbolBadge(value);
                const icon = resolveSymbolIcon(value);
                const row = document.createElement('button');
                row.type = 'button';
                row.dataset.symbolsOption = value;
                row.className = `w-full flex items-center justify-between gap-3 rounded-lg px-2.5 sm:px-3 py-2 border transition-colors ${isSelected ? 'border-[#00E676]/40 bg-[#00E676]/10' : 'border-transparent hover:border-[#2A2A2A] hover:bg-[#121212]'}`;
                row.innerHTML = `
                    <span class="flex items-center gap-2 min-w-0">
                        ${icon
                            ? `<img src="${icon}" alt="${label}" class="w-5 h-5 rounded-full object-cover">`
                            : `<span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold" style="background:${badge.color}20;color:${badge.color}">${badge.glyph}</span>`
                        }
                        <span class="text-sm font-mono text-white truncate">${label}</span>
                    </span>
                    <span class="w-5 h-5 rounded-md border border-[#2A2A2A] flex items-center justify-center ${isSelected ? 'text-[#00E676] bg-[#00E676]/10' : 'text-transparent'}">
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m5 13 4 4L19 7"></path></svg>
                    </span>
                `;
                return row;
            };

            const renderSymbolOptions = () => {
                if (!optionsContainer) return;
                optionsContainer.innerHTML = '';
                const query = String(searchInput?.value || '').trim().toUpperCase();
                const available = [allOption, ...symbolOptions].filter(Boolean).filter((option) => {
                    const label = option.value === '__ALL__' ? 'ВСЕ МОНЕТЫ' : option.value.toUpperCase();
                    return query === '' || label.includes(query);
                });

                if (available.length === 0) {
                    const empty = document.createElement('div');
                    empty.className = 'px-3 py-2 text-xs font-mono text-[#555555] border border-dashed border-[#2A2A2A] rounded-lg';
                    empty.textContent = 'Ничего не найдено';
                    optionsContainer.appendChild(empty);
                    return;
                }

                available.forEach((option) => {
                    const label = option.value === '__ALL__' ? 'Все монеты' : option.value;
                    const row = createOptionRow(option.value, label, option.selected);
                    optionsContainer.appendChild(row);
                });
            };

            const refreshUi = () => {
                normalizeSymbolsSelection();
                updateDropdownLabel();
                renderSymbolChips();
                renderSymbolOptions();
            };

            symbolsSelect.addEventListener('change', refreshUi);

            if (chipsContainer) {
                chipsContainer.addEventListener('click', (event) => {
                    const removeButton = event.target.closest('[data-remove-symbol]');
                    if (!removeButton) return;
                    const value = removeButton.getAttribute('data-remove-symbol');
                    if (!value) return;
                    if (value === '__ALL__') {
                        if (allOption) allOption.selected = false;
                    } else {
                        const option = symbolOptions.find((item) => item.value === value);
                        if (option) option.selected = false;
                    }
                    refreshUi();
                });
            }

            if (dropdownToggle) {
                dropdownToggle.addEventListener('click', () => {
                    if (isDropdownOpen()) {
                        closeDropdown();
                    } else {
                        openDropdown();
                    }
                });
            }

            if (optionsContainer) {
                optionsContainer.addEventListener('click', (event) => {
                    event.stopPropagation();
                    const button = event.target.closest('[data-symbols-option]');
                    if (!button) return;
                    const value = button.getAttribute('data-symbols-option');
                    if (!value) return;

                    if (value === '__ALL__') {
                        selectPortfolio();
                    } else {
                        if (allOption) allOption.selected = false;
                        const option = symbolOptions.find((item) => item.value === value);
                        if (option) option.selected = !option.selected;
                    }
                    refreshUi();
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    renderSymbolOptions();
                });
                searchInput.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        closeDropdown();
                        return;
                    }
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        const firstOption = optionsContainer?.querySelector('[data-symbols-option]');
                        if (!firstOption) return;
                        firstOption.click();
                    }
                });
            }

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    closeDropdown();
                }
            });

            if (selectAllButton) {
                selectAllButton.addEventListener('click', () => {
                    selectPortfolio();
                    refreshUi();
                });
            }

            if (clearButton) {
                clearButton.addEventListener('click', () => {
                    clearSelection();
                    refreshUi();
                });
            }

            form.addEventListener('submit', () => {
                const hasSelected = allOptions.some((option) => option.selected);
                if (!hasSelected && allOption) {
                    selectPortfolio();
                }
                refreshUi();
            });

            refreshUi();
        });
    };

    const initLeveragePicker = () => {
        document.querySelectorAll('[data-leverage-picker]').forEach((picker) => {
            if (picker.dataset.boundLeveragePicker === 'true') return;
            picker.dataset.boundLeveragePicker = 'true';

            const hiddenSelect = picker.querySelector('[data-leverage-select]');
            const toggle = picker.querySelector('[data-leverage-toggle]');
            const label = picker.querySelector('[data-leverage-label]');
            const menu = picker.querySelector('[data-leverage-menu]');
            const options = Array.from(picker.querySelectorAll('[data-leverage-option]'));
            if (!hiddenSelect || !toggle || !label || !menu || options.length === 0) return;

            const close = () => {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            };

            const open = () => {
                menu.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            };

            const sync = () => {
                const selected = String(hiddenSelect.value || '1');
                label.textContent = `x${selected}`;
                options.forEach((option) => {
                    const value = option.getAttribute('data-leverage-option');
                    const isActive = value === selected;
                    option.classList.toggle('border-[#00E676]/50', isActive);
                    option.classList.toggle('bg-[#00E676]/15', isActive);
                    option.classList.toggle('text-[#00E676]', isActive);
                    option.classList.toggle('border-[#2A2A2A]', !isActive);
                    option.classList.toggle('bg-[#121212]', !isActive);
                    option.classList.toggle('text-[#BBBBBB]', !isActive);
                    option.classList.toggle('hover:border-[#404040]', !isActive);
                    option.classList.toggle('hover:text-white', !isActive);
                });
            };

            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (menu.classList.contains('hidden')) {
                    open();
                } else {
                    close();
                }
            });

            options.forEach((option) => {
                option.addEventListener('click', (event) => {
                    event.preventDefault();
                    const value = option.getAttribute('data-leverage-option');
                    if (!value) return;
                    hiddenSelect.value = value;
                    hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    sync();
                    close();
                });
            });

            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    close();
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    close();
                }
            });

            sync();
        });
    };

    const initCapitalPicker = () => {
        document.querySelectorAll('[data-capital-picker]').forEach((picker) => {
            if (picker.dataset.boundCapitalPicker === 'true') return;
            picker.dataset.boundCapitalPicker = 'true';

            const input = picker.querySelector('input[name="capital"]');
            const options = Array.from(picker.querySelectorAll('[data-capital-option]'));
            if (!input || options.length === 0) return;

            const styleOption = (button, active) => {
                button.classList.toggle('border-[#00E676]/45', active);
                button.classList.toggle('bg-[#00E676]/12', active);
                button.classList.toggle('text-[#00E676]', active);
                button.classList.toggle('border-[#2A2A2A]', !active);
                button.classList.toggle('bg-[#121212]', !active);
                button.classList.toggle('text-[#AAAAAA]', !active);
                button.classList.toggle('hover:border-[#404040]', !active);
                button.classList.toggle('hover:text-white', !active);
            };

            const sync = () => {
                const value = Math.round(Number(input.value || 0));
                options.forEach((button) => {
                    const buttonValue = Math.round(Number(button.getAttribute('data-capital-option') || 0));
                    styleOption(button, buttonValue === value);
                });
            };

            options.forEach((button) => {
                button.addEventListener('click', () => {
                    const raw = Number(button.getAttribute('data-capital-option') || 0);
                    if (!Number.isFinite(raw) || raw <= 0) return;
                    input.value = String(raw);
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    sync();
                });
            });

            input.addEventListener('input', sync);
            input.addEventListener('change', sync);
            sync();
        });
    };

    window.addEventListener('resize', scheduleDrawAll);
    window.addEventListener('load', scheduleDrawAll);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            scheduleDrawAll();
        }
    });
    document.addEventListener('DOMContentLoaded', () => {
        scheduleDrawAll();
        initEquityControls();
        initScrollTopButton();
        initSymbolSelect();
        initPerformanceControls();
        initLeveragePicker();
        initCapitalPicker();
    });
    document.addEventListener('livewire:initialized', () => {
        scheduleDrawAll();
        initEquityControls();
        initScrollTopButton();
        initSymbolSelect();
        initPerformanceControls();
        initLeveragePicker();
        initCapitalPicker();
        if (window.Livewire && typeof window.Livewire.hook === 'function') {
            window.Livewire.hook('morphed', () => {
                scheduleDrawAll();
                initEquityControls();
                initScrollTopButton();
                initSymbolSelect();
                initPerformanceControls();
                initLeveragePicker();
                initCapitalPicker();
            });
        }
    });
})();

(() => {
    const historyLoadMoreText = @json(__('custom.performance.show_more'));
    const historyLoadingText = @json(__('custom.performance.loading'));
    const findHistorySection = () => document.querySelector('[data-history-section]');
    const findLoadButton = (root = document) => root.querySelector('[data-history-load-more]');
    const initHistorySortPicker = (root = document) => {
        root.querySelectorAll('[data-history-sort-picker]').forEach((picker) => {
            if (picker.dataset.boundSortPicker === '1') {
                return;
            }
            picker.dataset.boundSortPicker = '1';

            const select = picker.querySelector('[data-history-sort]');
            const toggle = picker.querySelector('[data-history-sort-toggle]');
            const label = picker.querySelector('[data-history-sort-label]');
            const menu = picker.querySelector('[data-history-sort-menu]');
            const options = Array.from(picker.querySelectorAll('[data-history-sort-option]'));
            if (!select || !toggle || !label || !menu || options.length === 0) {
                return;
            }

            const syncLabel = () => {
                const selectedOption = select.options[select.selectedIndex];
                label.textContent = selectedOption ? selectedOption.textContent || '' : '';
            };

            const close = () => {
                menu.classList.add('hidden');
                toggle.setAttribute('aria-expanded', 'false');
            };

            const open = () => {
                menu.classList.remove('hidden');
                toggle.setAttribute('aria-expanded', 'true');
            };

            toggle.addEventListener('click', (event) => {
                event.preventDefault();
                event.stopPropagation();
                if (menu.classList.contains('hidden')) {
                    open();
                } else {
                    close();
                }
            });

            options.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    const value = button.getAttribute('data-history-sort-option');
                    if (!value) return;
                    select.value = value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    close();
                });
            });

            select.addEventListener('change', syncLabel);
            document.addEventListener('click', (event) => {
                if (!picker.contains(event.target)) {
                    close();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    close();
                }
            });

            syncLabel();
        });
    };

    const bindHistoryControls = () => {
        const section = findHistorySection();
        if (!section || section.dataset.historyControlsBound === '1') {
            return;
        }

        const filterButtons = Array.from(section.querySelectorAll('[data-history-direction]'));
        const sortSelect = section.querySelector('[data-history-sort]');
        const tbody = section.querySelector('tbody');
        const cardsContainer = section.querySelector('[data-history-cards]');
        const rows = tbody ? Array.from(tbody.querySelectorAll('[data-history-row="true"]')) : [];
        const cards = cardsContainer ? Array.from(cardsContainer.querySelectorAll('[data-history-card="true"]')) : [];
        if (rows.length === 0 && cards.length === 0) {
            section.dataset.historyControlsBound = '1';
            return;
        }

        let activeDirection = 'all';

        const syncFilterButtons = () => {
            filterButtons.forEach((button) => {
                const direction = button.getAttribute('data-history-direction') || 'all';
                button.dataset.active = direction === activeDirection ? 'true' : 'false';
            });
        };

        const toNumber = (value) => {
            const n = Number(value);
            return Number.isFinite(n) ? n : 0;
        };

        const compareEntries = (a, b, sortKey) => {
            const aDate = toNumber(a.dataset.date);
            const bDate = toNumber(b.dataset.date);
            const aRoi = toNumber(a.dataset.roi);
            const bRoi = toNumber(b.dataset.roi);
            const aTicker = String(a.dataset.ticker || '');
            const bTicker = String(b.dataset.ticker || '');

            switch (sortKey) {
                case 'date_asc': return aDate - bDate;
                case 'roi_desc': return bRoi - aRoi;
                case 'roi_asc': return aRoi - bRoi;
                case 'ticker_asc': return aTicker.localeCompare(bTicker);
                case 'ticker_desc': return bTicker.localeCompare(aTicker);
                case 'date_desc':
                default:
                    return bDate - aDate;
            }
        };

        const updateProgress = (shownCount) => {
            const progress = section.querySelector('[data-history-progress]');
            if (!progress) return;
            const total = Math.max(0, Number(section.dataset.historyTotal || 0));
            const pattern = section.dataset.historyProgressPattern || ':shown / :total';
            progress.textContent = pattern
                .replace(':shown', String(shownCount))
                .replace(':total', String(total));
        };

        const apply = () => {
            const sortKey = String(sortSelect?.value || 'date_desc');
            const filteredRows = rows.filter((row) => activeDirection === 'all' || row.dataset.direction === activeDirection);
            const filteredCards = cards.filter((card) => activeDirection === 'all' || card.dataset.direction === activeDirection);
            const sortedRows = [...filteredRows].sort((a, b) => compareEntries(a, b, sortKey));
            const sortedCards = [...filteredCards].sort((a, b) => compareEntries(a, b, sortKey));

            if (tbody) {
                sortedRows.forEach((row) => {
                    row.style.display = '';
                    tbody.appendChild(row);
                });
                rows.filter((row) => !filteredRows.includes(row)).forEach((row) => {
                    row.style.display = 'none';
                });
            }

            if (cardsContainer) {
                sortedCards.forEach((card) => {
                    card.style.display = '';
                    cardsContainer.appendChild(card);
                });
                cards.filter((card) => !filteredCards.includes(card)).forEach((card) => {
                    card.style.display = 'none';
                });
            }

            updateProgress(Math.max(filteredRows.length, filteredCards.length));
        };

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                activeDirection = button.getAttribute('data-history-direction') || 'all';
                syncFilterButtons();
                apply();
            });
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', apply);
        }

        syncFilterButtons();
        updateProgress(Math.max(0, Number(section.dataset.historyVisible || 0)));
        apply();
        section.dataset.historyControlsBound = '1';
    };

    const buildNextUrl = (section, nextLimit) => {
        const baseUrl = section?.dataset.historyUrl || window.location.href;
        const url = new URL(baseUrl, window.location.origin);
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.forEach((value, key) => {
            url.searchParams.append(key, value);
        });
        url.searchParams.set('history_limit', String(nextLimit));
        return url.toString();
    };

    const replaceHistorySection = (htmlText) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');
        const nextSection = doc.querySelector('[data-history-section]');
        const currentSection = findHistorySection();
        if (!nextSection || !currentSection) {
            return false;
        }

        currentSection.replaceWith(nextSection);
        return true;
    };

    const bindHistoryLoadMore = () => {
        const section = findHistorySection();
        const button = findLoadButton();
        if (!section || !button || button.dataset.historyBound === '1') {
            return;
        }

        button.dataset.historyBound = '1';
        button.addEventListener('click', async () => {
            const nextLimit = Number(button.dataset.historyNextLimit || 0);
            if (!Number.isFinite(nextLimit) || nextLimit <= 0) {
                return;
            }

            button.disabled = true;
            const originalText = button.textContent;
            button.textContent = historyLoadingText;

            try {
                const response = await fetch(buildNextUrl(section, nextLimit), {
                    method: 'GET',
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const htmlText = await response.text();
                const replaced = replaceHistorySection(htmlText);
                if (!replaced) {
                    throw new Error('History section not found in response');
                }

                bindHistoryLoadMore();
                initHistorySortPicker();
                bindHistoryControls();
            } catch (error) {
                button.disabled = false;
                button.textContent = originalText || historyLoadMoreText;
            }
        });
    };

    document.addEventListener('DOMContentLoaded', () => {
        initHistorySortPicker();
        bindHistoryLoadMore();
        bindHistoryControls();
    });
    document.addEventListener('livewire:navigated', () => {
        initHistorySortPicker();
        bindHistoryLoadMore();
        bindHistoryControls();
    });
})();
</script>

<script>
(() => {
    const MAIN_SELECTOR = '.performance-reference > main';

    const getMain = (root = document) => root.querySelector(MAIN_SELECTOR);

    const buildFormUrl = (form) => {
        const url = new URL(form.getAttribute('action') || window.location.href, window.location.origin);
        url.search = '';
        const data = new FormData(form);
        for (const [key, value] of data.entries()) {
            if (typeof value === 'string') {
                url.searchParams.append(key, value);
            }
        }
        return url.toString();
    };

    const replaceMainFromHtml = (htmlText) => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(htmlText, 'text/html');
        const nextMain = getMain(doc);
        const currentMain = getMain(document);
        if (!nextMain || !currentMain) {
            return false;
        }

        currentMain.replaceWith(nextMain);
        return true;
    };

    const triggerUiReinit = () => {
        document.dispatchEvent(new Event('livewire:initialized'));
        document.dispatchEvent(new Event('livewire:navigated'));
    };

    const fetchAndSwap = async (url, pushState = true) => {
        const response = await fetch(url, {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'text/html',
            },
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const htmlText = await response.text();
        if (!replaceMainFromHtml(htmlText)) {
            throw new Error('Backtest main block not found in response');
        }

        if (pushState) {
            window.history.pushState({}, '', url);
        }

        triggerUiReinit();
        bindAjaxBacktestForm();
    };

    const requestSubmitSafe = (form) => {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
    };

    const bindAjaxBacktestForm = () => {
        document.querySelectorAll('[data-performance-controls]').forEach((form) => {
            if (form.dataset.backtestAjaxBound === '1') {
                return;
            }
            form.dataset.backtestAjaxBound = '1';

            const submitButton = form.querySelector('button[type="submit"]');
            const autoApplyControls = form.querySelectorAll('input[name="preset"], input[name="period"], select[name="leverage"], select[name="strategy"]');
            const presetCards = form.querySelectorAll('label[for^="preset-"]');
            const leverageControl = form.querySelector('select[name="leverage"]');
            const customPresetInput = form.querySelector('input[name="preset"][value="custom"]');

            autoApplyControls.forEach((control) => {
                control.addEventListener('change', () => {
                    if (control === leverageControl && customPresetInput && !customPresetInput.checked) {
                        customPresetInput.checked = true;
                    }
                    requestSubmitSafe(form);
                });
            });

            presetCards.forEach((card) => {
                card.addEventListener('click', () => {
                    const targetId = card.getAttribute('for');
                    if (!targetId) return;
                    const input = form.querySelector(`#${targetId}`);
                    if (!input || input.checked) return;
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (form.dataset.backtestAjaxLoading === '1') {
                    return;
                }

                form.dataset.backtestAjaxLoading = '1';
                const originalText = submitButton ? submitButton.textContent : '';
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Загрузка...';
                }

                const url = buildFormUrl(form);

                try {
                    await fetchAndSwap(url, true);
                } catch (error) {
                    window.location.assign(url);
                } finally {
                    form.dataset.backtestAjaxLoading = '0';
                    if (submitButton && document.contains(submitButton)) {
                        submitButton.disabled = false;
                        submitButton.textContent = originalText;
                    }
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', bindAjaxBacktestForm);
    document.addEventListener('livewire:navigated', bindAjaxBacktestForm);
    window.addEventListener('popstate', () => {
        fetchAndSwap(window.location.href, false).catch(() => {
            window.location.reload();
        });
    });
})();
</script>
