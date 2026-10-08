<!DOCTYPE html>
<html @include('partials.timezone-attributes') lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $pageTitle = $title ?? null;
            $metaTitle = $pageTitle ? $pageTitle.' — '.config('app.name', 'BlackLine') : config('app.name', 'BlackLine');
            $metaDescription = $description ?? __('ui.meta.description');
            $metaUrl = url()->current();
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" sizes="512x512" href="{{ asset('favicon.png') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('favicon.png') }}">

        <title>{{ $metaTitle }}</title>
        <meta name="description" content="{{ $metaDescription }}">
        <link rel="canonical" href="{{ $metaUrl }}">
        <meta property="og:title" content="{{ $metaTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ $metaUrl }}">
        <meta property="og:site_name" content="{{ config('app.name', 'BlackLine') }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $metaTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;1,700&display=swap" rel="stylesheet">

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
    <body class="bl-shell font-sans selection:bg-accent selection:text-black">

        <div class="relative min-h-screen overflow-x-hidden">
            <div class="bl-ambient -left-40 top-0 h-[28rem] w-[28rem]"></div>
            <div class="bl-ambient -right-48 top-48 h-[30rem] w-[30rem] opacity-60"></div>

            <x-marketing.nav />

            <main class="relative z-10 pt-24">
                {{ $slot }}
            </main>

            <x-marketing.footer />
        </div>
        @livewireScripts
    </body>
</html>
