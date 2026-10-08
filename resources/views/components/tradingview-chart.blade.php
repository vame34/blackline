@props([
    'symbol' => null,
    'interval' => null,
    'class' => '',
    'studies' => [],
    'hideTopToolbar' => true,
    'hideSideToolbar' => true,
    'withDateRanges' => false,
    'allowSymbolChange' => false,
    'theme' => 'light',
])

@php
    $chartId = 'tv-chart-'.uniqid();
@endphp

<div
    id="{{ $chartId }}"
    class="{{ $class }}"
    data-tradingview-chart="true"
    data-symbol="{{ $symbol }}"
    data-interval="{{ $interval }}"
    data-locale="{{ app()->getLocale() }}"
    data-studies='@json($studies)'
    data-hide-top-toolbar="{{ $hideTopToolbar ? 'true' : 'false' }}"
    data-hide-side-toolbar="{{ $hideSideToolbar ? 'true' : 'false' }}"
    data-with-date-ranges="{{ $withDateRanges ? 'true' : 'false' }}"
    data-allow-symbol-change="{{ $allowSymbolChange ? 'true' : 'false' }}"
    data-theme="{{ $theme }}"
></div>
