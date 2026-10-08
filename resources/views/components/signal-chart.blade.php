@props([
    'candles' => [],
    'levels' => [],
    'markers' => [],
    'indicators' => [],
    'symbol' => null,
    'interval' => null,
    'exchange' => null,
    'bybitCategory' => null,
])

@php
    $hasData = is_array($candles) && count($candles) > 1;
@endphp

<div
    class="h-full w-full"
    data-signal-chart="true"
    data-candles='@json($candles)'
    data-levels='@json($levels)'
    data-markers='@json($markers)'
    data-indicators='@json($indicators)'
    data-symbol="{{ $symbol ? strtolower($symbol) : '' }}"
    data-interval="{{ $interval ? strtolower($interval) : '' }}"
    data-exchange="{{ $exchange ? strtolower($exchange) : '' }}"
    data-bybit-category="{{ $bybitCategory ? strtolower($bybitCategory) : '' }}"
    data-label-entry="{{ __('ui.signals.level_entry') }}"
    data-label-stop="{{ __('ui.signals.level_stop') }}"
    data-label-tp1="{{ __('ui.signals.level_tp1') }}"
    data-label-tp2="{{ __('ui.signals.level_tp2') }}"
    data-label-close="{{ __('ui.signals.level_close') }}"
>
    @if (!$hasData)
        <div class="flex h-full items-center justify-center text-sm text-slate-500">
            {{ __('ui.signals.chart_empty') }}
        </div>
    @endif
</div>
