@props(['series' => [], 'class' => ''])

@php
    $values = array_values(array_filter($series, static fn ($value) => is_numeric($value)));
    $maxPoints = 120;

    if (count($values) > $maxPoints) {
        $step = (count($values) - 1) / ($maxPoints - 1);
        $sampled = [];
        for ($i = 0; $i < $maxPoints; $i++) {
            $index = (int) round($i * $step);
            $sampled[] = $values[$index];
        }
        $values = $sampled;
    }

    $path = null;
    $fillPath = null;
    $points = [];
    $lastPoint = null;
    $lastValueLabel = null;

    if (count($values) > 1) {
        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        $width = 600;
        $height = 320;
        $padding = 16;
        $chartHeight = $height - ($padding * 2);
        $count = count($values);
        $linePoints = [];

        foreach ($values as $index => $value) {
            $x = $count === 1 ? 0 : ($index / ($count - 1)) * $width;
            $ratio = $range > 0 ? ($value - $min) / $range : 0.5;
            $y = $padding + (1 - $ratio) * $chartHeight;
            $linePoints[] = ['x' => $x, 'y' => $y, 'value' => (float) $value];
        }

        $path = 'M'.number_format($linePoints[0]['x'], 2, '.', '').','.number_format($linePoints[0]['y'], 2, '.', '');
        for ($i = 1; $i < $count; $i++) {
            $x0 = $linePoints[$i - 1]['x'];
            $y0 = $linePoints[$i - 1]['y'];
            $x1 = $linePoints[$i]['x'];
            $y1 = $linePoints[$i]['y'];
            $cx = ($x0 + $x1) / 2;
            $path .= ' C '.number_format($cx, 2, '.', '').','.number_format($y0, 2, '.', '').' '
                .number_format($cx, 2, '.', '').','.number_format($y1, 2, '.', '').' '
                .number_format($x1, 2, '.', '').','.number_format($y1, 2, '.', '');
        }

        $fillPath = $path.' L '.$width.' '.$height.' L 0 '.$height.' Z';

        $markerIndexes = array_values(array_unique([
            (int) floor(($count - 1) * 0.25),
            (int) floor(($count - 1) * 0.5),
            (int) floor(($count - 1) * 0.75),
        ]));
        foreach ($markerIndexes as $markerIndex) {
            if (isset($linePoints[$markerIndex])) {
                $points[] = $linePoints[$markerIndex];
            }
        }
        $lastPoint = $linePoints[$count - 1];
        $lastValueLabel = number_format((float) $lastPoint['value'], 2);
    }

    $chartId = 'equity-'.uniqid();
@endphp

<div class="relative h-full w-full overflow-hidden {{ $class }}">
    @if ($path)
        <svg viewBox="0 0 600 320" class="h-full w-full" preserveAspectRatio="none" aria-hidden="true">
            <defs>
                <linearGradient id="{{ $chartId }}" x1="0" x2="0" y1="0" y2="1">
                    <stop offset="0%" stop-color="#00ff88" stop-opacity="0.2"></stop>
                    <stop offset="100%" stop-color="#00ff88" stop-opacity="0"></stop>
                </linearGradient>
                <filter id="{{ $chartId }}-glow">
                    <feGaussianBlur stdDeviation="2.5" result="coloredBlur"></feGaussianBlur>
                    <feMerge>
                        <feMergeNode in="coloredBlur"></feMergeNode>
                        <feMergeNode in="SourceGraphic"></feMergeNode>
                    </feMerge>
                </filter>
            </defs>
            @for ($i = 0; $i < 5; $i++)
                <line x1="0" x2="600" y1="{{ number_format(16 + (($i * (320 - 32)) / 4), 2, '.', '') }}" y2="{{ number_format(16 + (($i * (320 - 32)) / 4), 2, '.', '') }}" stroke="rgba(255,255,255,0.05)" stroke-width="1"></line>
            @endfor

            <path d="{{ $fillPath }}" fill="url(#{{ $chartId }})"></path>
            <path d="{{ $path }}" fill="none" stroke="#00ff88" stroke-width="2" filter="url(#{{ $chartId }}-glow)"></path>

            @foreach ($points as $point)
                <circle cx="{{ number_format($point['x'], 2, '.', '') }}" cy="{{ number_format($point['y'], 2, '.', '') }}" r="3" fill="#000" stroke="#00ff88" stroke-width="2"></circle>
            @endforeach
            @if ($lastPoint)
                <circle cx="{{ number_format($lastPoint['x'], 2, '.', '') }}" cy="{{ number_format($lastPoint['y'], 2, '.', '') }}" r="4" fill="#00ff88" stroke="#fff" stroke-width="2" class="animate-pulse"></circle>
            @endif
        </svg>
        @if ($lastPoint)
            @php
                $leftPercent = ((float) $lastPoint['x'] / 600) * 100;
                $topPercent = ((float) $lastPoint['y'] / 320) * 100;
                $leftPercent = max(12.0, min(88.0, $leftPercent));
                $topPercent = max(14.0, min(86.0, $topPercent - 10.0));
            @endphp
            <div
                class="pointer-events-none absolute border border-[#00ff88]/50 bg-black/90 p-2 text-xs font-mono backdrop-blur-sm"
                style="left: {{ number_format($leftPercent, 2, '.', '') }}%; top: {{ number_format($topPercent, 2, '.', '') }}%; transform: translate(-50%, -100%);"
            >
                <div class="text-gray-400">LAST</div>
                <div class="font-bold text-[#00ff88]">{{ $lastValueLabel }}</div>
            </div>
        @endif
    @else
        <div class="flex h-full items-center justify-center text-sm text-slate-500">
            {{ __('ui.performance.equity_empty') }}
        </div>
    @endif
</div>
