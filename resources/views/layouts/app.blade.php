<!DOCTYPE html>
<html @include('partials.timezone-attributes') lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,700&display=swap" rel="stylesheet">

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
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="bl-shell font-sans antialiased">

        <div class="relative min-h-screen overflow-x-hidden">
            <div class="bl-ambient -left-40 -top-20 h-[24rem] w-[24rem]"></div>
            <div class="bl-ambient -right-40 top-40 h-[24rem] w-[24rem] opacity-50"></div>

            <x-marketing.nav />

            <div class="relative z-10 pt-24">
                @if (isset($header))
                    <header class="border-b border-white/10 bg-[#0A0A0A]/90">
                        <div class="bl-container py-5">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <main>
                    {{ $slot }}
                </main>
            </div>

            <x-marketing.footer />
        </div>
        @livewireScripts
    </body>
</html>
