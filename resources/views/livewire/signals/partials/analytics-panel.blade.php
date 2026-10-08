@php
    $indicatorScores = collect($customIndicators)->mapWithKeys(fn (array $i): array => [(string) $i['key'] => (int) $i['score']])->all();
    $trendHealth = (int) ($indicatorScores['trend_health'] ?? 0);
    $momentumQuality = (int) ($indicatorScores['momentum_quality'] ?? 0);
    $marketEfficiency = (int) ($indicatorScores['market_efficiency'] ?? 0);
    $breakoutReadiness = (int) ($indicatorScores['breakout_readiness'] ?? 0);
    $volatilityPressure = (int) ($indicatorScores['volatility_pressure'] ?? 0);
    $meanReversionEdge = (int) ($indicatorScores['mean_reversion_edge'] ?? 0);
    $trendExhaustion = (int) ($indicatorScores['trend_exhaustion'] ?? 0);
    $liquidityPressure = (int) ($indicatorScores['liquidity_pressure'] ?? 0);

    $trendPlay = (int) round(min(100, max(0, ($trendHealth * 0.45) + ($momentumQuality * 0.25) + ($marketEfficiency * 0.30))));
    $breakoutPlay = (int) round(min(100, max(0, ($breakoutReadiness * 0.55) + ($volatilityPressure * 0.30) + ((int) $regime['shiftProbability'] * 0.15))));
    $reversionPlay = (int) round(min(100, max(0, ($meanReversionEdge * 0.55) + ((100 - $trendHealth) * 0.25) + ((100 - $trendExhaustion) * 0.20))));

    $riskLabel = static function (int $readiness): string {
        if ($readiness >= 70) {
            return __('custom.analytics_panel.risk.low');
        }
        if ($readiness >= 45) {
            return __('custom.analytics_panel.risk.medium');
        }

        return __('custom.analytics_panel.risk.high');
    };

    $changes4h = [
        ['key' => 'momentum', 'label' => __('custom.analytics_panel.changes.momentum'), 'delta' => round(($momentumQuality - 50) / 5, 1)],
        ['key' => 'volatility', 'label' => __('custom.analytics_panel.changes.volatility'), 'delta' => round(($volatilityPressure - 50) / 5, 1)],
        ['key' => 'volume', 'label' => __('custom.analytics_panel.changes.volume'), 'delta' => round(($liquidityPressure - 50) / 5, 1)],
    ];
@endphp

<div class="rounded-2xl border border-white/10 bg-[#0E0E0E] p-5 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <div class="text-sm font-semibold text-white">{{ __('ui.analytics.title') }}</div>
            <div class="mt-1 text-xs text-subtext">{{ __('ui.analytics.subtitle') }}</div>
        </div>
        <div class="rounded-full bg-[#121212] px-3 py-1 text-xs font-semibold text-white">
            {{ (int) ($blackline['score'] ?? 0) }} / 100
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-white/10 bg-[#121212] p-3">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-subtext">{{ __('ui.analytics.blackline.bias') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ __('ui.analytics.bias.'.strtolower((string) ($blackline['bias'] ?? 'neutral'))) }}</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#121212] p-3">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-subtext">{{ __('ui.analytics.blackline.trend') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ (int) ($blackline['trend'] ?? 0) }}%</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#121212] p-3">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-subtext">{{ __('ui.analytics.blackline.momentum') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ (int) ($blackline['momentum'] ?? 0) }}%</div>
        </div>
        <div class="rounded-xl border border-white/10 bg-[#121212] p-3">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-subtext">{{ __('ui.analytics.blackline.risk') }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ (int) ($blackline['risk'] ?? 0) }}%</div>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-[#121212] p-4 xl:col-span-2">
            <div class="flex items-center justify-between">
                <div class="text-sm font-semibold text-white">{{ __('ui.analytics.regime.title') }}</div>
                <div class="text-base font-semibold text-white">{{ __('ui.analytics.regime.states.'.strtolower((string) $regime['state'])) }}</div>
            </div>
            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-white/10 bg-[#0E0E0E] p-3">
                    <div class="text-xs text-subtext">{{ __('ui.analytics.regime.state_confidence') }}</div>
                    <div class="mt-1 text-xl font-bold text-white">{{ $regime['confidence'] }}%</div>
                </div>
                <div class="rounded-xl border border-white/10 bg-[#0E0E0E] p-3">
                    <div class="text-xs text-subtext">{{ __('ui.analytics.regime.shift_probability') }}</div>
                    <div class="mt-1 text-xl font-bold {{ $regime['shiftProbability'] >= 60 ? 'text-rose-600' : 'text-white' }}">{{ $regime['shiftProbability'] }}%</div>
                </div>
            </div>
            <div class="mt-3 space-y-2">
                @foreach ($regime['nextStates'] as $state)
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs text-subtext">
                            <span>{{ __('ui.analytics.regime.states.'.strtolower((string) $state['name'])) }}</span>
                            <span>{{ $state['probability'] }}%</span>
                        </div>
                        <div class="h-2 rounded-full bg-slate-200">
                            <div class="h-2 rounded-full bg-[#121212]" style="width: {{ min(100, max(0, $state['probability'])) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-2xl border border-white/10 bg-[#121212] p-4">
            <div class="text-sm font-semibold text-white">{{ __('custom.analytics_panel.changes.title') }}</div>
            <div class="mt-3 space-y-3">
                @foreach ($changes4h as $change)
                    <div class="rounded-xl border border-white/10 bg-[#0E0E0E] p-3">
                        <div class="text-xs text-subtext">{{ $change['label'] }}</div>
                        <div class="mt-1 text-lg font-bold {{ $change['delta'] > 0 ? 'text-emerald-600' : ($change['delta'] < 0 ? 'text-rose-600' : 'text-white') }}">
                            {{ $change['delta'] > 0 ? '+' : '' }}{{ number_format($change['delta'], 1) }}%
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-3 md:grid-cols-3">
        @foreach ([
            ['title' => __('custom.analytics_panel.playbooks.trend.title'), 'readiness' => $trendPlay, 'trigger' => __('custom.analytics_panel.playbooks.trend.trigger')],
            ['title' => __('custom.analytics_panel.playbooks.breakout.title'), 'readiness' => $breakoutPlay, 'trigger' => __('custom.analytics_panel.playbooks.breakout.trigger')],
            ['title' => __('custom.analytics_panel.playbooks.reversion.title'), 'readiness' => $reversionPlay, 'trigger' => __('custom.analytics_panel.playbooks.reversion.trigger')],
        ] as $playbook)
            <div class="rounded-2xl border border-white/10 bg-[#121212] p-4">
                <div class="flex items-start justify-between gap-2">
                    <div class="text-sm font-semibold text-white">{{ $playbook['title'] }}</div>
                    <div class="text-lg font-bold {{ $playbook['readiness'] >= 70 ? 'text-emerald-600' : ($playbook['readiness'] >= 45 ? 'text-amber-600' : 'text-rose-600') }}">{{ $playbook['readiness'] }}%</div>
                </div>
                <div class="mt-2 text-xs text-subtext">{{ $playbook['trigger'] }}</div>
                <div class="mt-3 h-2 rounded-full bg-slate-200">
                    <div class="h-2 rounded-full bg-[#121212]" style="width: {{ $playbook['readiness'] }}%"></div>
                </div>
                <div class="mt-2 text-xs font-medium text-subtext">{{ $riskLabel($playbook['readiness']) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">
        <div class="text-sm font-semibold text-white">{{ __('ui.analytics.custom.title') }}</div>
        <div class="mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($customIndicators as $indicator)
                <div class="rounded-2xl border border-white/10 bg-[#121212] p-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="text-sm font-semibold text-white">{{ __('ui.analytics.custom.items.'.$indicator['key'].'.title') }}</div>
                            <div class="mt-1 text-xs text-subtext">{{ __('ui.analytics.custom.items.'.$indicator['key'].'.description') }}</div>
                        </div>
                        <div class="text-lg font-bold {{ $indicator['score'] >= 70 ? 'text-emerald-600' : ($indicator['score'] >= 45 ? 'text-amber-600' : 'text-rose-600') }}">{{ $indicator['score'] }}%</div>
                    </div>
                    <div class="mt-3 h-2 rounded-full bg-slate-200">
                        <div class="h-2 rounded-full bg-[#121212]" style="width: {{ $indicator['score'] }}%"></div>
                    </div>
                    <div class="mt-2 text-xs font-medium text-subtext">{{ __('ui.analytics.custom.status.'.$indicator['status']) }}</div>
                </div>
            @endforeach
        </div>
    </div>
</div>
