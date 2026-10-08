<!DOCTYPE html>
<html @include('partials.timezone-attributes') lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <title>{{ config('app.name', 'Blackline') }}</title>

        @php
            $reverbConfig = [
                'key' => config('broadcasting.connections.reverb.key'),
                'host' => config('broadcasting.connections.reverb.options.host') ?: request()->getHost(),
                'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
                'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
            ];
        @endphp
        <script>
            window.BlacklineReverb = @json($reverbConfig);
            window.BlacklineRealtimeEnabled = @json(request()->routeIs('signals.index'));
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;0,900;1,100;1,400;1,700;1,800;1,900&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <script src="https://unpkg.com/@phosphor-icons/web"></script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('head')
    </head>
    <body class="min-h-screen bg-black text-white antialiased">

        <div class="flex min-h-screen flex-col">
            <x-marketing.nav />

            <div class="flex-1">
                {{ $slot ?? '' }}
                @yield('content')
            </div>

            <x-marketing.footer />
        </div>

        @livewireScripts
        @stack('scripts')
    </body>
</html>
