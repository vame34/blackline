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

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-screen bg-black text-white antialiased">

        <div class="flex min-h-screen flex-col">
            <x-marketing.nav />
            <div class="flex-1">
                {{ $slot }}
            </div>

            <x-marketing.footer />
        </div>
        @livewireScripts
    </body>
</html>
