@php
    $isAllSymbols = ($symbol ?? null) === ($symbolAllValue ?? '__ALL__');
    $displaySymbol = $isAllSymbols ? 'ALL COINS' : strtoupper((string) $symbol);
    $chartSymbol = $isAllSymbols ? ($analysisSymbol ?? 'BTCUSDT') : (string) $symbol;
    $tvSymbol = $selectedInstrument['tradingview'] ?? ('BYBIT:'.$chartSymbol);
    $tvInterval = $analyticsTvInterval ?? '60';
    $coinLogoMap = [
        'BTC' => asset('images/coins/btc.png'),
        'ETH' => asset('images/coins/eth.png'),
        'BNB' => asset('images/coins/bnb.png'),
        'SOL' => asset('images/coins/sol.png'),
        'XRP' => asset('images/coins/xrp.png'),
        'DOGE' => asset('images/coins/doge.png'),
        'ADA' => asset('images/coins/ada.png'),
        'LINK' => asset('images/coins/link.png'),
        'XLM' => asset('images/coins/xlm.png'),
        'BCH' => asset('images/coins/bch.png'),
        'SUI' => asset('images/coins/sui.png'),
        'AVAX' => asset('images/coins/avax.png'),
        'SHIB' => asset('images/coins/shib.png'),
        'HBAR' => asset('images/coins/hbar.png'),
        'DOT' => asset('images/coins/dot.png'),
        'UNI' => asset('images/coins/uni.png'),
        'TRX' => asset('images/coins/trx.png'),
        'RUNE' => asset('images/coins/rune.png'),
        'MANA' => asset('images/coins/mana.png'),
        'LTC' => asset('images/coins/ltc.png'),
        'SAND' => asset('images/coins/sand.png'),
        'ATOM' => asset('images/coins/atom.png'),
        'FIL' => asset('images/coins/fil.png'),
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
    $displayBase = strtoupper(preg_replace('/USDT$/i', '', (string) $chartSymbol) ?? (string) $chartSymbol);
    $displayIconUrl = $coinLogoMap[$displayBase] ?? null;

    $topIndicators = collect($customIndicators)
        ->sortByDesc(fn (array $i) => (int) ($i['score'] ?? 0))
        ->take(5)
        ->values();

    $regimeState = (string) ($regime['state'] ?? 'neutral');
    $regimeConfidence = (int) ($regime['confidence'] ?? 0);
    $shiftProbability = (int) ($regime['shiftProbability'] ?? 0);

    $regimeStateLabel = trans('ui.analytics.regime.states.'.$regimeState);
    if ($regimeStateLabel === 'ui.analytics.regime.states.'.$regimeState) {
        $regimeStateLabel = ucfirst($regimeState);
    }

    $blacklineBars = [
        'trend' => __('ui.analytics.blackline.trend'),
        'momentum' => __('ui.analytics.blackline.momentum'),
        'risk' => __('ui.analytics.blackline.risk'),
    ];

    $latestSignal = $signals->first();
    $entryValue = $latestSignal ? (float) $latestSignal->entry_price : null;
    $stopValue = $latestSignal ? (float) $latestSignal->stop_loss : null;

    $tpTargets = $latestSignal ? array_values(array_filter(array_map('floatval', (array) $latestSignal->takeProfitTargets()), fn ($v) => $v > 0)) : [];
    $firstTpValue = count($tpTargets) ? (float) $tpTargets[0] : null;

    $highCandidates = array_values(array_filter([$entryValue, $firstTpValue], fn ($v) => is_numeric($v)));
    $lowCandidates = array_values(array_filter([$entryValue, $stopValue], fn ($v) => is_numeric($v)));

    $ohlcOpen = $entryValue;
    $ohlcHigh = count($highCandidates) ? max($highCandidates) : null;
    $ohlcLow = count($lowCandidates) ? min($lowCandidates) : null;
    $ohlcClose = $firstTpValue ?? $entryValue;

    $activeFiltersCount = collect([
        $status !== 'all' && $status !== '' ? 'status' : null,
        $direction !== '' ? 'direction' : null,
        $risk !== '' ? 'risk' : null,
        $sort !== 'recent' ? 'sort' : null,
        trim($searchSymbol) !== '' ? 'search' : null,
    ])->filter()->count();

    $feedFrom = $signals->firstItem() ?? 0;
    $feedTo = $signals->lastItem() ?? 0;
    $currentPage = $signals->currentPage();
    $lastPage = $signals->lastPage();
    $pageStart = max(1, min($currentPage, max(1, $lastPage - 2)));
    $pageEnd = min($lastPage, $pageStart + 2);
@endphp

<div class="terminal-reference flex min-h-screen w-full flex-col pt-24 font-sans text-sm selection:bg-neon selection:text-black">
    <main class="flex flex-1 min-h-0 overflow-x-hidden overflow-y-auto flex-wrap">
        <aside class="w-[18%] min-w-[240px] min-h-0 border-r border-white/10 bg-panel flex flex-col z-20">
            <div class="p-4 border-b border-white/5 bg-black">
                <h2 class="text-xs font-bold text-gray-500 uppercase tracking-[0.2em] mb-3">{{ __('ui.signals.terminal_universe') }}</h2>
                <div class="relative group" data-instrument-search-wrap>
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-600 text-xs group-hover:text-neon transition-colors"></i>
                    <input
                        type="text"
                        placeholder="SMART SEARCH..."
                        class="w-full bg-[#0F0F0F] border border-white/10 py-2.5 pl-9 pr-3 text-[11px] text-white placeholder-gray-700 focus:outline-none focus:border-neon/50 focus:bg-black transition-all uppercase font-mono tracking-wide"
                        data-instrument-search
                        wire:model.live.debounce.300ms="searchSymbol"
                    >
                </div>
            </div>

            <div class="flex-1 min-h-0 max-h-[calc(100vh-220px)] overflow-y-auto scrollbar-custom bg-[#080808]" data-instrument-list>
                <div class="flex flex-col">
                    @php
                        $allActive = $isAllSymbols;
                    @endphp
                    <button
                        type="button"
                        wire:key="instrument-all"
                        wire:click="selectSymbol('{{ $symbolAllValue ?? '__ALL__' }}')"
                        class="group flex items-center justify-between p-3 pl-4 border-l-2 {{ $allActive ? 'border-neon bg-white/5 relative overflow-hidden' : 'border-transparent hover:border-white/20 hover:bg-white/5 border-b border-white/5' }} transition-colors text-left w-full"
                        data-instrument-item
                        data-symbol="{{ $symbolAllValue ?? '__ALL__' }}"
                        data-label="ALL COINS"
                    >
                        @if ($allActive)
                            <div class="absolute inset-0 bg-gradient-to-r from-neon/5 to-transparent opacity-50"></div>
                        @endif
                        <div class="flex items-center gap-3 relative z-10">
                            <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold" style="background-color: #111827; color: #FFFFFF">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="flex flex-col">
                                <span class="font-bold text-sm tracking-tight {{ $allActive ? 'text-white group-hover:text-neon' : 'text-gray-400 group-hover:text-white' }} transition-colors">__ALL__</span>
                                <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wider group-hover:text-gray-300">All coins</span>
                            </div>
                        </div>
                        @if ($allActive)
                            <div class="px-1.5 py-0.5 border border-neon/30 bg-neon/5 text-[9px] text-neon font-mono font-bold tracking-wider relative z-10 shadow-[0_0_8px_rgba(0,255,136,0.1)]">{{ __('ui.signals.terminal_live') }}</div>
                        @endif
                    </button>
                    @foreach ($instrumentOptions as $option)
                        @php
                            $itemSymbol = strtoupper((string) $option['symbol']);
                            $active = $itemSymbol === strtoupper((string) $symbol);
                            $label = (string) ($option['label'] ?? $itemSymbol);
                            $clean = strtoupper(preg_replace('/USDT$/i', '', $itemSymbol) ?? $itemSymbol);
                            $coinIconUrl = $coinLogoMap[$clean] ?? null;
                            $initial = substr($clean, 0, 1) ?: 'C';
                            $coinPalette = ['#F7931A', '#627EEA', '#F0B90B', '#00FFA3', '#2A5ADA', '#8DC351', '#BA9F33', '#0033AD', '#FFFFFF', '#1F2937'];
                            $coinColor = $coinPalette[abs(crc32($itemSymbol)) % count($coinPalette)];
                            $coinText = $coinColor === '#FFFFFF' ? '#000000' : '#FFFFFF';
                            $iconMap = [
                                'BTC' => 'fab fa-bitcoin',
                                'ETH' => 'fab fa-ethereum',
                                'BNB' => 'fas fa-coins',
                                'XLM' => 'fas fa-rocket',
                            ];
                            $iconClass = $iconMap[$clean] ?? null;
                        @endphp

                        <button
                            type="button"
                            wire:key="instrument-{{ $itemSymbol }}"
                            wire:click="selectSymbol('{{ $itemSymbol }}')"
                            class="group flex items-center justify-between p-3 pl-4 border-l-2 {{ $active ? 'border-neon bg-white/5 relative overflow-hidden' : 'border-transparent hover:border-white/20 hover:bg-white/5 border-b border-white/5' }} transition-colors text-left w-full"
                            data-instrument-item
                            data-symbol="{{ $itemSymbol }}"
                            data-label="{{ strtoupper($label) }}"
                        >
                            @if ($active)
                                <div class="absolute inset-0 bg-gradient-to-r from-neon/5 to-transparent opacity-50"></div>
                            @endif
                            <div class="flex items-center gap-3 relative z-10">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold" style="background-color: {{ $coinColor }}; color: {{ $coinText }}">
                                    @if ($coinIconUrl)
                                        <img src="{{ $coinIconUrl }}" alt="{{ $clean }}" class="h-5 w-5 rounded-full object-cover" loading="lazy">
                                    @elseif ($iconClass)
                                        <i class="{{ $iconClass }}"></i>
                                    @else
                                        {{ $initial }}
                                    @endif
                                </div>
                                <div class="flex flex-col">
                                    <span class="font-bold text-sm tracking-tight {{ $active ? 'text-white group-hover:text-neon' : 'text-gray-400 group-hover:text-white' }} transition-colors">{{ $itemSymbol }}</span>
                                    <span class="text-[9px] text-gray-400 font-medium uppercase tracking-wider group-hover:text-gray-300">{{ $label }}</span>
                                </div>
                            </div>
                            @if ($active)
                                <div class="px-1.5 py-0.5 border border-neon/30 bg-neon/5 text-[9px] text-neon font-mono font-bold tracking-wider relative z-10 shadow-[0_0_8px_rgba(0,255,136,0.1)]">{{ __('ui.signals.terminal_live') }}</div>
                            @endif
                        </button>
                    @endforeach
                </div>
            </div>
        </aside>

        @if (! $isAllSymbols)
        <section class="flex-1 flex flex-col min-w-0 border-r border-white/10 bg-black relative">
            <div class="h-16 border-b border-white/10 flex items-center justify-between px-6 bg-panel">
                <div class="flex items-center gap-6">
                    <div class="flex items-center gap-3">
                        @if ($displayIconUrl)
                            <img src="{{ $displayIconUrl }}" alt="{{ $displayBase }}" class="h-8 w-8 rounded-full object-cover" loading="lazy">
                        @endif
                        <div>
                            <h1 class="text-2xl font-bold header-italic tracking-tighter text-white">{{ $displaySymbol }}</h1>
                            <span class="text-[10px] font-mono text-gray-500 uppercase tracking-wider">{{ $tvSymbol }}</span>
                        </div>
                    </div>
                    <div class="h-8 w-[1px] bg-white/10"></div>
                    <div class="flex items-center gap-8">
                        <div>
                            <span class="block text-[9px] text-gray-500 uppercase tracking-widest mb-0.5">{{ __('ui.analytics.blackline.title') }}</span>
                            <div class="flex items-baseline gap-1">
                                <span class="font-mono text-lg font-bold text-white text-shadow-neon">{{ (int) ($blackline['score'] ?? 0) }}</span>
                                <span class="text-gray-600 text-xs font-mono">/100</span>
                            </div>
                        </div>
                        <div>
                            <span class="block text-[9px] text-gray-500 uppercase tracking-widest mb-0.5">{{ __('ui.analytics.blackline.risk') }}</span>
                            <span class="font-mono text-lg font-bold {{ ((int) ($blackline['risk'] ?? 0)) >= 60 ? 'text-danger' : 'text-neon' }}">{{ (int) ($blackline['risk'] ?? 0) }}%</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="flex-1 min-h-[420px] relative w-full bg-[#050505] overflow-hidden border-b border-white/10 group">
                <div class="absolute top-3 left-3 flex gap-px z-10 shadow-lg">
                    <button class="w-8 h-8 flex items-center justify-center border border-white/10 bg-panel hover:bg-white/5 hover:border-neon/50 text-gray-400 hover:text-white transition-colors" type="button">
                        <i class="fas fa-layer-group text-xs"></i>
                    </button>
                    <div class="relative h-8 border border-white/10 bg-panel transition-colors hover:border-neon/50 hover:bg-white/5">
                        <select
                            class="h-full min-w-[78px] appearance-none bg-transparent pl-3 pr-7 text-xs font-mono text-gray-300 outline-none"
                            wire:model.live="analyticsTimeframe"
                            aria-label="{{ __('ui.signals.filters.timeframe') }}"
                        >
                            @foreach ($analyticsTimeframes as $tf)
                                <option value="{{ $tf }}" class="bg-panel text-white">
                                    {{ $analyticsTimeframeLabels[$tf] ?? $tf }}
                                </option>
                            @endforeach
                        </select>
                        <i class="fas fa-chevron-down pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[10px] text-gray-500"></i>
                    </div>
                    <button class="px-3 h-8 flex items-center justify-center border border-white/10 bg-panel hover:bg-white/5 hover:border-neon/50 text-xs text-gray-400 hover:text-white font-mono transition-colors border-l-0" type="button">Indicators</button>
                    <button class="w-8 h-8 flex items-center justify-center border border-white/10 bg-panel hover:bg-white/5 hover:border-neon/50 text-gray-400 hover:text-white transition-colors border-l-0" type="button">
                        <i class="fas fa-camera text-xs"></i>
                    </button>
                </div>

                <div class="absolute inset-0 grid grid-cols-6 grid-rows-4 pointer-events-none">
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-b border-white/5"></div>

                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-b border-white/5"></div>

                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-r border-white/5 border-b border-white/5"></div>
                    <div class="border-b border-white/5"></div>

                    <div class="border-r border-white/5"></div>
                    <div class="border-r border-white/5"></div>
                    <div class="border-r border-white/5"></div>
                    <div class="border-r border-white/5"></div>
                    <div class="border-r border-white/5"></div>
                </div>

                <div class="absolute inset-0 w-full h-full p-6 pt-12 pb-4">
                    <div class="h-full w-full border border-white/10 bg-black/80">
                        <x-tradingview-chart
                            :symbol="$tvSymbol"
                            :interval="$tvInterval"
                            theme="dark"
                            class="h-full w-full"
                            wire:key="tv-chart-{{ $chartKey }}-{{ $tvSymbol }}-{{ $tvInterval }}"
                        />
                    </div>
                </div>

                <div class="absolute bottom-0 left-0 right-0 h-16 flex items-end justify-between px-8 gap-0.5 opacity-20 pointer-events-none">
                    <div class="w-2 h-4 bg-danger"></div>
                    <div class="w-2 h-6 bg-neon"></div>
                    <div class="w-2 h-3 bg-danger"></div>
                    <div class="w-2 h-8 bg-neon"></div>
                    <div class="w-2 h-5 bg-danger"></div>
                    <div class="w-2 h-10 bg-neon"></div>
                    <div class="w-2 h-4 bg-danger"></div>
                    <div class="w-2 h-2 bg-neon"></div>
                    <div class="w-2 h-7 bg-danger"></div>
                    <div class="w-2 h-5 bg-neon"></div>
                    <div class="w-2 h-9 bg-danger"></div>
                    <div class="w-2 h-6 bg-neon"></div>
                    <div class="w-2 h-3 bg-danger"></div>
                    <div class="w-2 h-8 bg-neon"></div>
                    <div class="w-2 h-4 bg-danger"></div>
                    <div class="w-2 h-7 bg-neon"></div>
                    <div class="w-2 h-9 bg-neon"></div>
                    <div class="w-2 h-12 bg-danger"></div>
                    <div class="w-2 h-4 bg-neon"></div>
                </div>
            </div>

        </section>
        @endif

        <aside class="signals-window {{ $isAllSymbols ? 'flex-1 min-w-0 border-l border-white/10 shadow-[-10px_0_30px_rgba(0,0,0,0.5)]' : 'w-[32%] min-w-[320px] border-l border-white/10 shadow-[-10px_0_30px_rgba(0,0,0,0.5)]' }} min-h-0 bg-panel flex flex-col z-20">
            <div class="signals-window__top p-4 border-b border-white/10 bg-black">
                <div class="signals-window__heading flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2">
                        <h2 class="signals-window__title text-xs font-bold uppercase tracking-[0.2em] text-gray-500">{{ __('ui.signals.terminal_live_signals') }}</h2>
                        <span class="signals-window__count bg-white/10 text-white text-[9px] px-1.5 py-0.5 rounded-sm font-mono">{{ $signals->total() }}</span>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <button type="button" class="signals-window__icon-btn w-7 h-7 flex items-center justify-center border border-white/10 bg-panel hover:bg-white/5 hover:border-neon/30 text-gray-400 hover:text-white transition-colors">
                            <i class="fas fa-filter text-[10px]"></i>
                        </button>
                        <button type="button" wire:click="$refresh" class="signals-window__icon-btn w-7 h-7 flex items-center justify-center border border-white/10 bg-panel hover:bg-white/5 hover:border-white/30 text-gray-400 hover:text-white transition-colors">
                            <i class="fas fa-cog text-[10px]"></i>
                        </button>
                    </div>
                </div>
                <div class="signals-window__meta mt-3 flex flex-wrap items-center gap-2">
                    <span class="signals-window__badge signals-window__badge--neon border border-neon/30 bg-neon/10 text-[9px] px-1.5 py-0.5 rounded-sm font-mono text-neon">
                        {{ __('ui.signals.terminal_winrate_1y') }}:
                        {{ $yearWinRate !== null ? number_format($yearWinRate * 100, 2) . '%' : '—' }}
                    </span>
                    <span class="signals-window__badge signals-window__badge--neon border border-white/10 bg-white/5 text-[9px] px-1.5 py-0.5 rounded-sm font-mono text-gray-200">
                        {{ __('ui.signals.terminal_sum_pct_1y') }}:
                        {{ ($yearSumPct >= 0 ? '+' : '') . number_format($yearSumPct * 100, 2) . '%' }}
                    </span>
                    <span
                        class="signals-window__timezone font-mono text-[10px] text-gray-400"
                        data-user-timezone-label
                        data-timezone-prefix="{{ __('ui.signals.terminal_timezone') }}"
                    >
                        {{ __('ui.signals.terminal_timezone_detecting') }}
                    </span>
                </div>
            </div>

            <div class="signals-window__filters grid gap-3 border-b border-white/10 bg-[#060606] px-4 py-3">

                <label class="text-[9px] font-mono uppercase tracking-wider text-gray-500">
                    <span class="mb-1 block">{{ __('ui.signals.strategy') }}</span>
                    <select wire:model.live="strategy" class="signals-window__control h-9 w-full border border-white/10 bg-black/70 px-2 text-[11px] text-white focus:border-neon/50 focus:outline-none">
                        @foreach($strategyOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-[9px] font-mono uppercase tracking-wider text-gray-500">
                    <span class="mb-1 block">{{ __('ui.signals.terminal_direction') }}</span>
                    <select wire:model.live="direction" class="signals-window__control h-9 w-full border border-white/10 bg-black/70 px-2 text-[11px] text-white focus:border-neon/50 focus:outline-none">
                        <option value="">{{ __('ui.signals.terminal_direction_any') }}</option>
                        <option value="LONG">LONG</option>
                        <option value="SHORT">SHORT</option>
                    </select>
                </label>

                <label class="text-[9px] font-mono uppercase tracking-wider text-gray-500">
                    <span class="mb-1 block">{{ __('ui.signals.terminal_outcome') }}</span>
                    <select wire:model.live="outcome" class="signals-window__control h-9 w-full border border-white/10 bg-black/70 px-2 text-[11px] text-white focus:border-neon/50 focus:outline-none">
                        <option value="all">{{ __('ui.signals.terminal_outcome_all') }}</option>
                        <option value="win">{{ __('ui.signals.terminal_outcome_win') }}</option>
                        <option value="loss">{{ __('ui.signals.terminal_outcome_loss') }}</option>
                    </select>
                </label>

                <label class="text-[9px] font-mono uppercase tracking-wider text-gray-500">
                    <span class="mb-1 block">{{ __('ui.signals.terminal_date_from') }}</span>
                    <input wire:model.live="dateFrom" type="date" class="signals-window__control h-9 w-full border border-white/10 bg-black/70 px-2 text-[11px] text-white focus:border-neon/50 focus:outline-none">
                </label>

                <label class="text-[9px] font-mono uppercase tracking-wider text-gray-500">
                    <span class="mb-1 block">{{ __('ui.signals.terminal_date_to') }}</span>
                    <input wire:model.live="dateTo" type="date" class="signals-window__control h-9 w-full border border-white/10 bg-black/70 px-2 text-[11px] text-white focus:border-neon/50 focus:outline-none">
                </label>
            </div>

            @if (! $isAllSymbols)
                <div class="signals-window__head sticky top-0 z-10 grid grid-cols-[1fr_0.7fr_1fr_0.7fr_0.8fr] gap-3 border-b border-white/10 bg-[#080808] px-5 py-3 text-[9px] font-bold uppercase tracking-wider text-gray-500">
                    <div>{{ __('ui.signals.filters.symbol') }}</div>
                    <div>{{ __('ui.signals.filters.direction') }}</div>
                    <div class="text-right">{{ __('ui.signals.terminal_entry') }} / TP / {{ __('ui.signals.terminal_stop') }}</div>
                    <div class="text-center">{{ __('ui.signals.status') }}</div>
                    <div class="text-right">{{ __('ui.signals.terminal_result') }}</div>
                </div>
            @endif

            <div class="signals-window__feed scrollbar-custom flex-1 min-h-0 max-h-[calc(100vh-260px)] overflow-y-auto bg-[#080808] {{ $isAllSymbols ? 'p-4' : '' }}" data-terminal-feed>
                @if ($isAllSymbols)
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-3">
                        @foreach ($signals as $signal)
                            @php
                                $resultPct = $signal->resultPct();
                                $resultText = $resultPct !== null
                                    ? (($resultPct > 0 ? '+' : '') . number_format($resultPct * 100, 2) . '%')
                                    : __('ui.common.na');
                                $resultClass = $resultPct === null
                                    ? 'text-gray-500'
                                    : ($resultPct > 0 ? 'text-neon' : ($resultPct < 0 ? 'text-danger' : 'text-gray-300'));
                                $isLong = $signal->direction->value === 'LONG';
                                $isActiveSignal = $signal->status->value !== 'closed';
                                $formatPrice = static function ($value): string {
                                    if ($value === null) {
                                        return __('ui.common.na');
                                    }
                                    $formatted = number_format((float) $value, 8, '.', '');
                                    return rtrim(rtrim($formatted, '0'), '.');
                                };
                            @endphp
                            <div class="signals-window__row group relative grid grid-cols-[1fr_0.7fr_1fr_0.7fr_0.8fr] items-center gap-3 border border-white/10 px-4 py-3 transition-colors hover:bg-white/5">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-white transition-colors {{ $isLong ? 'group-hover:text-neon' : 'group-hover:text-danger' }}">{{ $signal->symbol }}</span>
                                    <span
                                        class="text-[9px] text-gray-600 font-mono"
                                        data-local-datetime
                                        data-utc-datetime="{{ $signal->published_at?->toIso8601String() }}"
                                    >{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($signal->published_at, 'd.m H:i') }}</span>
                                </div>

                                <div>
                                    <span class="signals-window__direction text-[9px] font-bold uppercase tracking-wide {{ $isLong ? 'signals-window__direction--long border border-neon/20 bg-neon/10 text-neon shadow-[0_0_5px_rgba(0,255,136,0.1)]' : 'signals-window__direction--short border border-danger/20 bg-danger/10 text-danger shadow-[0_0_5px_rgba(255,51,51,0.1)]' }}">
                                        <span class="signals-window__direction-text">{{ __('ui.trading.direction.'.$signal->direction->value) }}</span>
                                    </span>
                                </div>

                                <div class="text-right font-mono text-xs text-white">
                                    {{ $formatPrice($signal->entry_price) }}
                                    <span class="mt-0.5 block text-[9px] font-medium text-neon">
                                        {{ $signal->take_profit !== null ? $formatPrice($signal->take_profit) : __('ui.common.na') }}
                                    </span>
                                    <span class="mt-0.5 block text-[9px] font-medium text-danger">
                                        {{ $signal->stop_loss !== null ? $formatPrice($signal->stop_loss) : __('ui.common.na') }}
                                    </span>
                                </div>

                                <div class="text-center">
                                    @if ($isActiveSignal)
                                        <span class="signals-window__status-open rounded-sm bg-white/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">{{ __('ui.signals.terminal_status_open') }}</span>
                                    @else
                                        <span class="signals-window__status-closed text-[9px] uppercase tracking-wide text-gray-500">{{ __('ui.signals.terminal_status_closed') }}</span>
                                    @endif
                                </div>

                                <div class="text-right font-mono text-xs font-bold {{ $resultClass }}">
                                    {{ $resultText }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    @foreach ($signals as $signal)
                        @php
                            $resultPct = $signal->resultPct();
                            $resultText = $resultPct !== null
                                ? (($resultPct > 0 ? '+' : '') . number_format($resultPct * 100, 2) . '%')
                                : __('ui.common.na');
                            $resultClass = $resultPct === null
                                ? 'text-gray-500'
                                : ($resultPct > 0 ? 'text-neon' : ($resultPct < 0 ? 'text-danger' : 'text-gray-300'));
                            $isLong = $signal->direction->value === 'LONG';
                            $isActiveSignal = $signal->status->value !== 'closed';
                            $isHighlighted = in_array($signal->id, $highlightedSignalIds, true);
                            $formatPrice = static function ($value): string {
                                if ($value === null) {
                                    return __('ui.common.na');
                                }

                                $formatted = number_format((float) $value, 8, '.', '');

                                return rtrim(rtrim($formatted, '0'), '.');
                            };
                        @endphp
                        <div class="signals-window__row group relative grid grid-cols-[1fr_0.7fr_1fr_0.7fr_0.8fr] items-center gap-3 border-b border-white/5 px-5 py-3.5 transition-colors hover:bg-white/5 {{ $isHighlighted ? 'bg-white/[0.02]' : '' }}">
                            @if ($isHighlighted)
                                <div class="absolute bottom-0 left-0 top-0 w-[2px] bg-neon shadow-[0_0_10px_#00ff88]"></div>
                            @endif

                            <div class="flex flex-col">
                                <span class="text-xs font-bold text-white transition-colors {{ $isLong ? 'group-hover:text-neon' : 'group-hover:text-danger' }}">{{ $signal->symbol }}</span>
                                <span
                                    class="text-[9px] text-gray-600 font-mono"
                                    data-local-datetime
                                    data-utc-datetime="{{ $signal->published_at?->toIso8601String() }}"
                                >{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($signal->published_at, 'd.m H:i') }}</span>
                            </div>

                            <div>
                                <span class="signals-window__direction text-[9px] font-bold uppercase tracking-wide {{ $isLong ? 'signals-window__direction--long border border-neon/20 bg-neon/10 text-neon shadow-[0_0_5px_rgba(0,255,136,0.1)]' : 'signals-window__direction--short border border-danger/20 bg-danger/10 text-danger shadow-[0_0_5px_rgba(255,51,51,0.1)]' }}">
                                    <span class="signals-window__direction-text">{{ __('ui.trading.direction.'.$signal->direction->value) }}</span>
                                </span>
                            </div>

                            <div class="text-right font-mono text-xs text-white">
                                {{ $formatPrice($signal->entry_price) }}
                                <span class="mt-0.5 block text-[9px] font-medium text-neon">
                                    {{ $signal->take_profit !== null ? $formatPrice($signal->take_profit) : __('ui.common.na') }}
                                </span>
                                <span class="mt-0.5 block text-[9px] font-medium text-danger">
                                    {{ $signal->stop_loss !== null ? $formatPrice($signal->stop_loss) : __('ui.common.na') }}
                                </span>
                            </div>

                            <div class="text-center">
                                @if ($isActiveSignal)
                                    <span class="signals-window__status-open rounded-sm bg-white/10 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white">{{ __('ui.signals.terminal_status_open') }}</span>
                                @else
                                    <span class="signals-window__status-closed text-[9px] uppercase tracking-wide text-gray-500">{{ __('ui.signals.terminal_status_closed') }}</span>
                                @endif
                            </div>

                            <div class="text-right font-mono text-xs font-bold {{ $resultClass }}">
                                {{ $resultText }}
                            </div>
                        </div>
                    @endforeach
                @endif

                @if ($signals->isEmpty())
                    <div class="flex h-full items-center justify-center px-8 text-center">
                        <div>
                            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-white/5 text-lg text-gray-500">◌</div>
                            <p class="text-sm text-gray-500">{{ __('ui.signals.empty_title') }}</p>
                        </div>
                    </div>
                @endif
            </div>

            <div class="signals-window__footer p-3 border-t border-white/10 flex items-center justify-between bg-black">
                <span class="signals-window__range text-[9px] text-gray-600 uppercase tracking-wide font-medium">{{ $feedFrom }}-{{ $feedTo }} of {{ $signals->total() }}</span>
                <div class="signals-window__pagination flex items-center gap-1.5">
                    <button type="button" wire:click="previousPage" class="signals-window__page-btn w-6 h-6 flex items-center justify-center border border-white/10 text-[9px] transition-colors {{ $signals->onFirstPage() ? 'opacity-40 cursor-not-allowed text-gray-600' : 'hover:border-white text-gray-400 hover:text-white' }}" {{ $signals->onFirstPage() ? 'disabled' : '' }}>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    @for ($page = $pageStart; $page <= $pageEnd; $page++)
                        <button type="button" wire:click="gotoPage({{ $page }})" class="signals-window__page-btn w-6 h-6 flex items-center justify-center text-[9px] {{ $page === $currentPage ? 'border border-neon bg-neon/10 text-neon font-bold shadow-[0_0_5px_rgba(0,255,136,0.2)] signals-window__page-btn--active' : 'border border-white/10 text-gray-400 hover:border-white hover:text-white transition-colors' }}">
                            {{ $page }}
                        </button>
                    @endfor
                    @if ($pageEnd < $lastPage)
                        <span class="signals-window__ellipsis text-gray-600 text-[10px] px-0.5">...</span>
                    @endif
                    <button type="button" wire:click="nextPage" class="signals-window__page-btn w-6 h-6 flex items-center justify-center border border-white/10 text-[9px] transition-colors {{ $signals->hasMorePages() ? 'hover:border-white text-gray-400 hover:text-white' : 'opacity-40 cursor-not-allowed text-gray-600' }}" {{ $signals->hasMorePages() ? '' : 'disabled' }}>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </aside>
    </main>
</div>
