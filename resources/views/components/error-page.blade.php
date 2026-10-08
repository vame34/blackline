@props([
    'code',
    'title',
    'message',
])

@php
    $themes = [
        '500' => ['accent' => '#ff3b3b', 'status' => __('ui.errors.status.degraded')],
        '503' => ['accent' => '#f59e0b', 'status' => __('ui.errors.status.maintenance')],
        '404' => ['accent' => '#3b82f6', 'status' => __('ui.errors.status.notice')],
        '403' => ['accent' => '#a855f7', 'status' => __('ui.errors.status.notice')],
        '429' => ['accent' => '#eab308', 'status' => __('ui.errors.status.limited')],
        '419' => ['accent' => '#06b6d4', 'status' => __('ui.errors.status.notice')],
    ];

    $theme = $themes[(string) $code] ?? ['accent' => '#00ff88', 'status' => __('ui.errors.status.notice')];
    $accent = $theme['accent'];
    $statusText = $theme['status'];

    $hexToRgba = static function (string $hex, float $alpha): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = preg_replace('/(.)/', '$1$1', $hex);
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return "rgba({$r}, {$g}, {$b}, {$alpha})";
    };

    $accent15 = $hexToRgba($accent, 0.15);
    $accent40 = $hexToRgba($accent, 0.40);
    $accent50 = $hexToRgba($accent, 0.50);
    $accent60 = $hexToRgba($accent, 0.60);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

    <title>BlackLine | {{ $code }} Error</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #000000;
            color: #fff;
            overflow-x: hidden;
            font-family: Inter, sans-serif;
        }

        .bg-grid {
            background-size: 40px 40px;
            background-image:
                linear-gradient(to right, rgba(255, 255, 255, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
        }

        .scanline {
            width: 100%;
            height: 100%;
            position: fixed;
            left: 0;
            top: 0;
            pointer-events: none;
            background: linear-gradient(
                to bottom,
                rgba(255, 255, 255, 0),
                rgba(255, 255, 255, 0) 50%,
                rgba(0, 0, 0, 0.1) 50%,
                rgba(0, 0, 0, 0.1)
            );
            background-size: 100% 4px;
            z-index: 50;
        }

        .glow-text {
            text-shadow: 0 0 30px {{ $accent60 }}, 0 0 10px {{ $accent40 }};
        }

        .glow-box {
            box-shadow: 0 0 100px {{ $accent15 }};
        }

        .unskew {
            transform: skewX(12deg);
        }

        .skew-btn {
            transform: skewX(-12deg);
        }

        @keyframes glitch-anim {
            0% {
                transform: translate(0);
            }
            20% {
                transform: translate(-2px, 2px);
            }
            40% {
                transform: translate(-2px, -2px);
            }
            60% {
                transform: translate(2px, 2px);
            }
            80% {
                transform: translate(2px, -2px);
            }
            100% {
                transform: translate(0);
            }
        }

        .glitch-line {
            animation: glitch-anim 2s infinite linear alternate-reverse;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-100%);
            }
            100% {
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body class="relative flex min-h-screen flex-col bg-grid selection:bg-[#00ff88] selection:text-black">

    <div class="scanline"></div>
    <div
        class="pointer-events-none absolute left-1/2 top-1/2 z-0 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full blur-[150px]"
        style="background-color: {{ $accent }}; opacity: 0.08;"
    ></div>

    <x-marketing.nav />

    <main class="relative z-10 flex flex-grow items-center justify-center px-4 py-12 pt-32">
        <div class="glow-box relative w-full max-w-2xl border border-white/10 bg-[rgba(0,0,0,0.72)] p-12 backdrop-blur-md">
            <div class="absolute right-8 top-8">
                <a href="{{ url()->previous() }}" class="skew-btn group border border-white/20 px-4 py-1.5 transition-all hover:border-white/60 hover:bg-white/5">
                    <span class="unskew inline-block font-mono text-[10px] uppercase tracking-widest text-white/60 group-hover:text-white">
                        {{ __('ui.errors.back') }}
                    </span>
                </a>
            </div>

            <div class="mt-4 flex flex-col items-center text-center">
                <div class="mb-2 flex items-center gap-3">
                    <div class="h-[1px] w-8" style="background-color: {{ $accent50 }}"></div>
                    <span class="font-mono text-xs uppercase tracking-[0.2em] text-white/40">{{ __('ui.errors.code') }}</span>
                    <div class="h-[1px] w-8" style="background-color: {{ $accent50 }}"></div>
                </div>

                <h1 class="glow-text relative text-[140px] font-black italic leading-[0.8] tracking-tighter mix-blend-screen" style="color: {{ $accent }}">
                    {{ $code }}
                    <div class="glitch-line absolute -right-8 top-1/2 h-[2px] w-16 opacity-50" style="background-color: {{ $accent }}"></div>
                    <div class="glitch-line absolute -left-8 top-1/3 h-[2px] w-8 opacity-30 delay-75" style="background-color: {{ $accent }}"></div>
                </h1>

                <div class="relative my-10 h-[1px] w-full overflow-hidden bg-white/10">
                    <div
                        class="absolute left-0 top-0 h-full w-full -translate-x-full"
                        style="background: linear-gradient(to right, transparent, {{ $accent40 }}, transparent); animation: shimmer 2s infinite;"
                    ></div>
                </div>

                <h2 class="mb-4 text-3xl font-black italic uppercase tracking-tight text-white md:text-4xl">{{ $title }}</h2>
                <p class="mx-auto mb-10 max-w-md font-mono text-sm leading-relaxed text-white/50">
                    {{ $message }}<br>
                    <span class="mt-2 block text-xs text-white/20">SERVER_ERROR::{{ $code }}</span>
                </p>

                <div class="mb-8 flex flex-wrap justify-center gap-4">
                    <a href="{{ route('home') }}" class="skew-btn group min-w-[120px] border border-white/20 px-8 py-3 transition-all hover:bg-[#00ff88]/10 hover:shadow-[0_0_15px_rgba(0,255,136,0.2)] hover:border-[#00ff88]">
                        <span class="unskew inline-block text-xs font-bold italic tracking-wider text-white transition-colors group-hover:text-[#00ff88]">{{ __('ui.errors.go_home') }}</span>
                    </a>
                    <a href="{{ route('performance.terminal') }}" class="skew-btn group min-w-[120px] border border-white/20 px-8 py-3 transition-all hover:bg-[#00ff88]/10 hover:shadow-[0_0_15px_rgba(0,255,136,0.2)] hover:border-[#00ff88]">
                        <span class="unskew inline-block text-xs font-bold italic tracking-wider text-white transition-colors group-hover:text-[#00ff88]">{{ __('ui.nav.performance') }}</span>
                    </a>
                    <a href="{{ route('signals.index') }}" class="skew-btn group min-w-[120px] border border-white/20 px-8 py-3 transition-all hover:bg-[#00ff88]/10 hover:shadow-[0_0_15px_rgba(0,255,136,0.2)] hover:border-[#00ff88]">
                        <span class="unskew inline-block text-xs font-bold italic tracking-wider text-white transition-colors group-hover:text-[#00ff88]">{{ __('ui.errors.go_dashboard') }}</span>
                    </a>
                </div>

                <div class="mb-6 h-[1px] w-24 bg-white/10"></div>
                <p class="font-mono text-[10px] uppercase tracking-widest text-white/30">{{ __('ui.signals.not_advice') }}</p>
            </div>

            <div class="absolute left-0 top-0 h-2 w-2 border-l border-t border-[#00ff88]"></div>
            <div class="absolute right-0 top-0 h-2 w-2 border-r border-t border-[#00ff88]"></div>
            <div class="absolute bottom-0 left-0 h-2 w-2 border-b border-l border-[#00ff88]"></div>
            <div class="absolute bottom-0 right-0 h-2 w-2 border-b border-r border-[#00ff88]"></div>
        </div>
    </main>

    <x-marketing.footer />
</body>
</html>
