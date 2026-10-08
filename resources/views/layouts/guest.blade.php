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
    <body class="bl-shell font-sans antialiased">

        <div class="relative flex min-h-screen flex-col overflow-x-hidden">
            <div class="bl-ambient -left-40 top-10 h-[22rem] w-[22rem]"></div>
            <div class="bl-ambient -right-40 bottom-0 h-[20rem] w-[20rem] opacity-50"></div>

            <div class="relative z-10 flex flex-1 items-center justify-center px-4 py-10">
                <div class="w-full max-w-md">
                    <div class="mb-8 flex justify-center">
                        <a href="{{ route('home') }}" wire:navigate>
                            <x-application-logo class="h-14 w-auto" />
                        </a>
                    </div>

                    <div class="bl-panel p-6 sm:p-7">
                        {{ $slot }}
                    </div>
                </div>
            </div>

            <x-marketing.footer />
        </div>
        @livewireScripts
    </body>
</html>
