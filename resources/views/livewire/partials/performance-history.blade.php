<section
    class="glass-panel overflow-hidden"
    data-history-section
    data-history-url="{{ route('performance.terminal.history') }}"
    data-history-total="{{ $historyTotal }}"
    data-history-visible="{{ $historyVisible }}"
    data-history-progress-pattern="{{ __('custom.performance.history_shown_of', ['shown' => ':shown', 'total' => ':total']) }}"
>
    <div class="p-6 border-b border-white/10 flex justify-between items-center">
        <h3 class="text-xl font-bold italic text-white">{{ __('custom.performance.history') }}</h3>
    </div>

    <div class="p-6 border-b border-white/10 flex flex-wrap items-center gap-4" data-history-controls>
        <div class="flex items-center gap-2 rounded-lg border border-white/10 bg-black/30 p-1" data-history-filter>
            <button type="button" class="px-3 py-1.5 text-[10px] font-mono uppercase tracking-wider text-white/70 transition-colors hover:text-white data-[active=true]:bg-[#00ff88]/20 data-[active=true]:text-[#00ff88]" data-history-direction="all">{{ __('custom.performance.filter_all') }}</button>
            <button type="button" class="px-3 py-1.5 text-[10px] font-mono uppercase tracking-wider text-white/70 transition-colors hover:text-white data-[active=true]:bg-[#00ff88]/20 data-[active=true]:text-[#00ff88]" data-history-direction="LONG">{{ __('custom.performance.filter_long') }}</button>
            <button type="button" class="px-3 py-1.5 text-[10px] font-mono uppercase tracking-wider text-white/70 transition-colors hover:text-white data-[active=true]:bg-[#ff3b3b]/20 data-[active=true]:text-[#ff3b3b]" data-history-direction="SHORT">{{ __('custom.performance.filter_short') }}</button>
        </div>
        <div class="w-full sm:w-auto sm:ml-auto flex items-center gap-2 text-xs font-mono text-gray-400">
            <span>{{ __('custom.performance.sort_by') }}</span>
            <div class="relative w-full sm:w-auto" data-history-sort-picker>
                <button
                    type="button"
                    data-history-sort-toggle
                    class="w-full sm:min-w-[190px] bg-black/30 border border-white/10 text-white px-3 py-1.5 text-xs font-mono uppercase tracking-wider rounded-lg flex items-center justify-between gap-2 hover:border-[#00ff88]/30 transition-colors"
                    aria-expanded="false"
                >
                    <span data-history-sort-label>{{ __('custom.performance.sort_date_desc') }}</span>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m19 9-7 7-7-7"></path>
                    </svg>
                </button>
                <div class="hidden absolute z-30 right-0 mt-2 min-w-full sm:min-w-[230px] rounded-lg border border-white/10 bg-[#0B0B0B] p-1.5 shadow-[0_10px_30px_rgba(0,0,0,0.4)]" data-history-sort-menu>
                    <button type="button" data-history-sort-option="date_desc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_date_desc') }}</button>
                    <button type="button" data-history-sort-option="date_asc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_date_asc') }}</button>
                    <button type="button" data-history-sort-option="roi_desc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_roi_desc') }}</button>
                    <button type="button" data-history-sort-option="roi_asc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_roi_asc') }}</button>
                    <button type="button" data-history-sort-option="ticker_asc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_ticker_asc') }}</button>
                    <button type="button" data-history-sort-option="ticker_desc" class="w-full text-left rounded-md px-2.5 py-2 text-xs font-mono text-white/90 hover:bg-[#00ff88]/10 hover:text-[#00ff88] transition-colors">{{ __('custom.performance.sort_ticker_desc') }}</button>
                </div>
                <select class="hidden" data-history-sort>
                    <option value="date_desc">{{ __('custom.performance.sort_date_desc') }}</option>
                    <option value="date_asc">{{ __('custom.performance.sort_date_asc') }}</option>
                    <option value="roi_desc">{{ __('custom.performance.sort_roi_desc') }}</option>
                    <option value="roi_asc">{{ __('custom.performance.sort_roi_asc') }}</option>
                    <option value="ticker_asc">{{ __('custom.performance.sort_ticker_asc') }}</option>
                    <option value="ticker_desc">{{ __('custom.performance.sort_ticker_desc') }}</option>
                </select>
            </div>
        </div>
    </div>

    <div class="hidden lg:block overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-black/40 border-b border-white/10 text-xs font-mono text-gray-500 uppercase tracking-widest">
                    <th class="py-4 px-6 font-medium">{{ __('custom.performance.ticker') }}</th>
                    <th class="py-4 px-6 font-medium">{{ __('custom.performance.direction') }}</th>
                    <th class="py-4 px-6 font-medium text-right">{{ __('custom.performance.entry') }}</th>
                    <th class="py-4 px-6 font-medium text-right">{{ __('custom.performance.exit') }}</th>
                    <th class="py-4 px-6 font-medium text-right">ROI</th>
                    <th class="py-4 px-6 font-medium text-right">R:R</th>
                    <th class="py-4 px-6 font-medium text-right">{{ __('custom.performance.trade_capital') }}</th>
                    <th class="py-4 px-6 font-medium text-right">{{ __('custom.performance.balance_after') }}</th>
                    <th class="py-4 px-6 font-medium text-right">{{ __('custom.performance.date') }}</th>
                    <th class="py-4 px-6 font-medium text-center">{{ __('custom.performance.status') }}</th>
                </tr>
            </thead>
            <tbody class="text-sm font-mono">
                @forelse ($closedSignals as $signal)
                    @php
                        $isLong = $signal->direction->value === 'LONG';
                        $resultPct = $signal->resultPct();
                        $leveredResultPct = $resultPct !== null ? ($resultPct * ((float) $selectedLeverage)) : null;
                        $resultPositive = $leveredResultPct !== null ? $leveredResultPct >= 0 : false;
                        $symbolBase = strtoupper((string) preg_replace('/USDT$/i', '', (string) $signal->symbol));
                        $symbolIcon = $coinIconMap[$symbolBase] ?? null;
                        $balanceAfter = $historyBalanceBySignalId[$signal->id] ?? null;
                        $tradeNotional = $historyTradeNotionalBySignalId[$signal->id] ?? null;
                        $tradeMargin = $historyTradeMarginBySignalId[$signal->id] ?? null;
                        $rewardRisk = $signal->reward_risk !== null ? (float) $signal->reward_risk : null;
                        $closedTimestamp = $signal->closed_at?->getTimestamp() ?? 0;
                    @endphp
                    <tr
                        class="border-b border-white/5 {{ $resultPositive ? 'hover:bg-[#00ff88]/5' : 'hover:bg-[#ff3b3b]/5' }} transition-colors group"
                        data-history-row="true"
                        data-direction="{{ $signal->direction->value }}"
                        data-roi="{{ $leveredResultPct !== null ? number_format($leveredResultPct * 100, 4, '.', '') : '' }}"
                        data-date="{{ $closedTimestamp }}"
                        data-ticker="{{ strtolower((string) $signal->symbol) }}"
                        data-rr="{{ $rewardRisk !== null ? number_format($rewardRisk, 4, '.', '') : '' }}"
                    >
                        <td class="py-4 px-6 font-bold text-white flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full {{ $isLong ? 'bg-[#00ff88]/20 border-[#00ff88]/30' : 'bg-[#ff3b3b]/20 border-[#ff3b3b]/30' }} flex items-center justify-center text-xs font-bold border overflow-hidden">
                                @if ($symbolIcon)
                                    <img src="{{ $symbolIcon }}" alt="{{ $symbolBase }}" class="h-7 w-7 rounded-full object-cover" loading="lazy">
                                @else
                                    <span class="{{ $isLong ? 'text-[#00ff88]' : 'text-[#ff3b3b]' }}">{{ substr($signal->symbol, 0, 1) }}</span>
                                @endif
                            </div>
                            {{ $signal->symbol }}
                        </td>
                        <td class="py-4 px-6">
                            <span class="{{ $isLong ? 'text-[#00ff88] bg-[#00ff88]/10 border-[#00ff88]/20' : 'text-[#ff3b3b] bg-[#ff3b3b]/10 border-[#ff3b3b]/20' }} px-2 py-1 text-xs font-bold uppercase tracking-wider border">{{ $signal->direction->value }}</span>
                        </td>
                        <td class="py-4 px-6 text-right text-gray-300">{{ number_format((float) $signal->entry_price, 2) }}</td>
                        <td class="py-4 px-6 text-right text-gray-300">{{ $signal->close_price !== null ? number_format((float) $signal->close_price, 2) : '—' }}</td>
                        <td class="py-4 px-6 text-right {{ $resultPositive ? 'text-[#00ff88]' : 'text-[#ff3b3b]' }} font-bold">{{ $leveredResultPct !== null ? ($leveredResultPct > 0 ? '+' : '') . number_format($leveredResultPct * 100, 1) . '%' : '—' }}</td>
                        <td class="py-4 px-6 text-right text-gray-200 font-bold">{{ $rewardRisk !== null ? '1:' . number_format($rewardRisk, 1, '.', '') : '—' }}</td>
                        <td class="py-4 px-6 text-right text-gray-200 font-bold">
                            {{ $tradeNotional !== null ? '$' . number_format((float) $tradeNotional, 2, '.', ' ') : '—' }}
                            @if ($tradeMargin !== null)
                                <span class="block text-[10px] text-gray-500 font-normal">M: ${{ number_format((float) $tradeMargin, 2, '.', ' ') }}</span>
                            @endif
                        </td>
                        <td class="py-4 px-6 text-right text-white font-bold">{{ $balanceAfter !== null ? '$' . number_format((float) $balanceAfter, 2, '.', ' ') : '—' }}</td>
                        <td class="py-4 px-6 text-right text-gray-500" data-local-datetime data-include-year="true" data-utc-datetime="{{ $signal->closed_at?->toIso8601String() }}">{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($signal->closed_at, 'd.m.Y H:i') }}</td>
                        <td class="py-4 px-6 text-center">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 border {{ $signal->status->value === 'closed' ? 'border-[#00ff88]/30 text-[#00ff88] bg-[#00ff88]/5' : 'border-[#ff3b3b]/30 text-[#ff3b3b] bg-[#ff3b3b]/5' }} text-[10px] font-bold uppercase tracking-wider">
                                <span class="w-1.5 h-1.5 rounded-full {{ $signal->status->value === 'closed' ? 'bg-[#00ff88]' : 'bg-[#ff3b3b]' }}"></span> {{ __('custom.performance.status_' . strtolower((string) $signal->status->value)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="py-10 px-6 text-center text-sm text-gray-500">{{ __('custom.performance.no_signals') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="lg:hidden divide-y divide-white/10" data-history-cards>
        @forelse ($closedSignals as $signal)
            @php
                $isLong = $signal->direction->value === 'LONG';
                $resultPct = $signal->resultPct();
                $leveredResultPct = $resultPct !== null ? ($resultPct * ((float) $selectedLeverage)) : null;
                $resultPositive = $leveredResultPct !== null ? $leveredResultPct >= 0 : false;
                $symbolBase = strtoupper((string) preg_replace('/USDT$/i', '', (string) $signal->symbol));
                $symbolIcon = $coinIconMap[$symbolBase] ?? null;
                $balanceAfter = $historyBalanceBySignalId[$signal->id] ?? null;
                $tradeNotional = $historyTradeNotionalBySignalId[$signal->id] ?? null;
                $tradeMargin = $historyTradeMarginBySignalId[$signal->id] ?? null;
                $rewardRisk = $signal->reward_risk !== null ? (float) $signal->reward_risk : null;
                $closedTimestamp = $signal->closed_at?->getTimestamp() ?? 0;
            @endphp
            <article
                class="p-4 space-y-3 {{ $resultPositive ? 'bg-[#00ff88]/[0.02]' : 'bg-[#ff3b3b]/[0.02]' }}"
                data-history-card="true"
                data-direction="{{ $signal->direction->value }}"
                data-roi="{{ $leveredResultPct !== null ? number_format($leveredResultPct * 100, 4, '.', '') : '' }}"
                data-date="{{ $closedTimestamp }}"
                data-ticker="{{ strtolower((string) $signal->symbol) }}"
                data-rr="{{ $rewardRisk !== null ? number_format($rewardRisk, 4, '.', '') : '' }}"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-8 h-8 rounded-full {{ $isLong ? 'bg-[#00ff88]/20 border-[#00ff88]/30' : 'bg-[#ff3b3b]/20 border-[#ff3b3b]/30' }} flex items-center justify-center text-xs font-bold border overflow-hidden shrink-0">
                            @if ($symbolIcon)
                                <img src="{{ $symbolIcon }}" alt="{{ $symbolBase }}" class="h-7 w-7 rounded-full object-cover" loading="lazy">
                            @else
                                <span class="{{ $isLong ? 'text-[#00ff88]' : 'text-[#ff3b3b]' }}">{{ substr($signal->symbol, 0, 1) }}</span>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-white truncate">{{ $signal->symbol }}</div>
                            <div class="text-[11px] text-gray-500" data-local-datetime data-include-year="true" data-utc-datetime="{{ $signal->closed_at?->toIso8601String() }}">{{ app(\App\Services\Preferences\DisplayTimezone::class)->format($signal->closed_at, 'd.m.Y H:i') }}</div>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-2 py-1 border {{ $signal->status->value === 'closed' ? 'border-[#00ff88]/30 text-[#00ff88] bg-[#00ff88]/5' : 'border-[#ff3b3b]/30 text-[#ff3b3b] bg-[#ff3b3b]/5' }} text-[10px] font-bold uppercase tracking-wider shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full {{ $signal->status->value === 'closed' ? 'bg-[#00ff88]' : 'bg-[#ff3b3b]' }}"></span> {{ __('custom.performance.status_' . strtolower((string) $signal->status->value)) }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3">
                    <span class="{{ $isLong ? 'text-[#00ff88] bg-[#00ff88]/10 border-[#00ff88]/20' : 'text-[#ff3b3b] bg-[#ff3b3b]/10 border-[#ff3b3b]/20' }} px-2 py-1 text-xs font-bold uppercase tracking-wider border">{{ $signal->direction->value }}</span>
                    <span class="text-sm font-bold {{ $resultPositive ? 'text-[#00ff88]' : 'text-[#ff3b3b]' }}">
                        {{ $leveredResultPct !== null ? ($leveredResultPct > 0 ? '+' : '') . number_format($leveredResultPct * 100, 1) . '%' : '—' }}
                    </span>
                </div>

                <dl class="grid grid-cols-2 gap-x-3 gap-y-2 text-xs font-mono">
                    <div>
                        <dt class="text-gray-500">{{ __('custom.performance.entry') }}</dt>
                        <dd class="text-gray-200">{{ number_format((float) $signal->entry_price, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('custom.performance.exit') }}</dt>
                        <dd class="text-gray-200">{{ $signal->close_price !== null ? number_format((float) $signal->close_price, 2) : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">R:R</dt>
                        <dd class="text-gray-200">{{ $rewardRisk !== null ? '1:' . number_format($rewardRisk, 1, '.', '') : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('custom.performance.balance_after') }}</dt>
                        <dd class="text-white">{{ $balanceAfter !== null ? '$' . number_format((float) $balanceAfter, 2, '.', ' ') : '—' }}</dd>
                    </div>
                </dl>

                <div class="text-xs font-mono text-gray-300">
                    <span class="text-gray-500">{{ __('custom.performance.trade_capital') }}: </span>
                    {{ $tradeNotional !== null ? '$' . number_format((float) $tradeNotional, 2, '.', ' ') : '—' }}
                    @if ($tradeMargin !== null)
                        <span class="block text-[10px] text-gray-500 mt-1">M: ${{ number_format((float) $tradeMargin, 2, '.', ' ') }}</span>
                    @endif
                </div>
            </article>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">{{ __('custom.performance.no_signals') }}</div>
        @endforelse
    </div>

    <div class="flex items-center justify-between gap-4 border-t border-white/10 px-6 py-4" data-history-footer>
        <div class="text-xs font-mono text-gray-500" data-history-progress>
            {{ __('custom.performance.history_shown_of', ['shown' => $historyVisible, 'total' => $historyTotal]) }}
        </div>
        @if ($historyVisible < $historyTotal)
            <button
                type="button"
                data-history-load-more
                data-history-next-limit="{{ $historyNextLimit }}"
                class="inline-flex h-9 items-center justify-center px-4 border border-[#00ff88]/40 bg-[#00ff88]/10 text-[#00ff88] text-xs font-mono uppercase tracking-wider leading-none hover:bg-[#00ff88]/20 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
            >
                {{ __('custom.performance.show_more') }}
            </button>
        @endif
    </div>
</section>
