<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-terminal')] class extends Component
{
    //
}; ?>

<style>
    .scanlines {
        position: fixed;
        inset: 0;
        pointer-events: none;
        z-index: 50;
    }

    .bg-grid {
        background-size: 40px 40px;
        background-image: radial-gradient(circle, #222 1px, transparent 1px);
    }

    .neon-border-glow:focus-within {
        box-shadow: 0 0 15px rgba(0, 255, 136, 0.2);
    }

    .checkbox-wrapper input:checked + div {
        background-color: #00ff88;
        border-color: #00ff88;
    }

    .checkbox-wrapper input:checked + div svg {
        display: block;
    }

    @keyframes pulse-glow {
        0%, 100% {
            opacity: 0.3;
            transform: scale(1);
        }
        50% {
            opacity: 0.5;
            transform: scale(1.1);
        }
    }

    .ambient-glow {
        animation: pulse-glow 8s infinite ease-in-out;
    }







</style>

<div class="relative flex min-h-full w-full flex-col items-center justify-center overflow-hidden bg-black text-white selection:bg-[#00ff88] selection:text-black">
    <x-system-preloader :autostart="false" />
    <div class="pointer-events-none absolute inset-0 bg-grid opacity-30"></div>
    <div class="ambient-glow pointer-events-none absolute left-1/2 top-1/2 h-[600px] w-[600px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[#00ff88] opacity-[0.08] blur-[150px]"></div>
    <div class="scanlines"></div>
    <div class="pointer-events-none fixed inset-0 z-40 bg-[radial-gradient(circle_at_center,transparent_50%,rgba(0,0,0,0.6)_100%)]"></div>

    <main class="relative z-10 flex w-full max-w-[440px] flex-col items-center gap-12 px-4 py-24">
        <div class="neon-border-glow relative w-full border border-white/10 bg-white/[0.02] p-8 backdrop-blur-sm transition-all duration-300">
            <div class="absolute -left-[1px] -top-[1px] h-4 w-4 border-l-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -right-[1px] -top-[1px] h-4 w-4 border-r-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -left-[1px] h-4 w-4 border-b-2 border-l-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -right-[1px] h-4 w-4 border-b-2 border-r-2 border-[#00ff88]"></div>

            <div class="mb-8 flex items-center">
                <div class="mr-3 h-4 w-1 bg-[#00ff88] shadow-[0_0_8px_#00ff88]"></div>
                <h2 class="text-xs uppercase tracking-[0.15em] text-gray-400">{{ __('custom.login.auth') }}</h2>
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form id="login-form" class="space-y-6" method="POST" action="{{ route('login.attempt') }}">
                @csrf
                <div class="group space-y-2">
                    <label for="email" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('custom.login.email') }}</label>
                    <div class="relative">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            autofocus
                            autocomplete="username"
                            class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]"
                            placeholder="user@example.com"
                        >
                    </div>
                    @error('email')
                        <p class="mt-2 text-xs text-[#ff6b6b]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="group space-y-2">
                    <label for="password" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('custom.login.password') }}</label>
                    <div class="relative">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]"
                            placeholder="••••••••••••"
                        >
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 transition-colors hover:text-[#00ff88]" onclick="const i=document.getElementById('password'); i.type=i.type==='password'?'text':'password';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    @error('password')
                        <p class="mt-2 text-xs text-[#ff6b6b]">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label for="remember" class="checkbox-wrapper group flex cursor-pointer items-center gap-3">
                        <input id="remember" type="checkbox" name="remember" value="1" @checked(old('remember')) class="hidden">
                        <div class="flex h-4 w-4 items-center justify-center border border-gray-600 bg-[#0a0a0a] transition-colors group-hover:border-[#00ff88]">
                            <svg class="hidden h-3 w-3 font-bold text-black" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="4">
                                <path stroke-linecap="square" stroke-linejoin="miter" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <span class="font-mono text-sm text-gray-400 transition-colors group-hover:text-gray-200">{{ __('custom.login.remember') }}</span>
                    </label>

                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" class="font-mono text-sm text-gray-400 transition-colors hover:text-[#00ff88] hover:underline" wire:navigate>{{ __('custom.login.forgot') }}</a>
                    @endif
                </div>

                <div class="flex justify-end pt-6">
                    <button type="submit" class="group relative">
                        <div class="transform -skew-x-12 bg-[#00ff88] px-10 py-3 transition-all duration-200 group-hover:translate-y-0 group-hover:bg-[#33ff99] group-hover:shadow-[0_0_20px_rgba(0,255,136,0.6)] group-active:translate-y-0.5">
                            <span class="block skew-x-12 text-base font-bold tracking-wider text-black">{{ __('custom.login.submit') }}</span>
                        </div>
                    </button>
                </div>

                <div class="pt-2">
                    <div class="mb-4 h-px w-full bg-white/10"></div>
                    </div>
            </form>
        </div>

        @if (Route::has('register'))
            <div class="group cursor-pointer text-center">
                <p class="text-xs uppercase tracking-[0.12em] text-gray-500 transition-colors group-hover:text-white">
                    {{ __('custom.login.no_account') }}
                    <a href="{{ route('register') }}" class="ml-2 text-[#00ff88] group-hover:underline" wire:navigate>{{ __('custom.login.register') }} →</a>
                </p>
            </div>
        @endif
    </main>

</div>

<script>
    (function () {
        const form = document.getElementById('login-form');
        if (!form) return;

        form.addEventListener('submit', function () {
            if (window.BlacklinePreloader && typeof window.BlacklinePreloader.startLogin === 'function') {
                window.BlacklinePreloader.startLogin();
            }
        });
    })();
</script>
