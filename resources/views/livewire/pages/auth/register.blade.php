<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-terminal')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ])));

        Auth::login($user);

        $this->redirect(route('signals.index', absolute: false), navigate: true);
    }
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

    <main class="relative z-10 flex w-full max-w-[520px] flex-col items-center gap-12 px-4 py-24">
        <div class="neon-border-glow relative w-full border border-white/10 bg-white/[0.02] p-8 backdrop-blur-sm transition-all duration-300">
            <div class="absolute -left-[1px] -top-[1px] h-4 w-4 border-l-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -right-[1px] -top-[1px] h-4 w-4 border-r-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -left-[1px] h-4 w-4 border-b-2 border-l-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -right-[1px] h-4 w-4 border-b-2 border-r-2 border-[#00ff88]"></div>

            <div class="mb-8 flex items-center">
                <div class="mr-3 h-4 w-1 bg-[#00ff88] shadow-[0_0_8px_#00ff88]"></div>
                <h2 class="text-xs uppercase tracking-[0.15em] text-gray-400">{{ __('custom.login.register') }}</h2>
            </div>

            <form class="space-y-5" wire:submit="register">
                <div class="h-px w-full bg-white/10"></div>

                <div class="group space-y-2">
                    <label for="name" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('Name') }}</label>
                    <input wire:model="name" id="name" class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]" type="text" name="name" required autofocus autocomplete="name" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div class="group space-y-2">
                    <label for="email" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('Email') }}</label>
                    <input wire:model="email" id="email" class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]" type="email" name="email" required autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="group space-y-2">
                    <label for="password" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('Password') }}</label>
                    <div class="relative">
                        <input wire:model="password" id="password" class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 pr-12 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]" type="password" name="password" required autocomplete="new-password" />
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 transition-colors hover:text-[#00ff88]" onclick="const i=document.getElementById('password'); i.type=i.type==='password'?'text':'password';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="group space-y-2">
                    <label for="password_confirmation" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('Confirm Password') }}</label>
                    <div class="relative">
                        <input wire:model="password_confirmation" id="password_confirmation" class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 pr-12 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]" type="password" name="password_confirmation" required autocomplete="new-password" />
                        <button type="button" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-600 transition-colors hover:text-[#00ff88]" onclick="const i=document.getElementById('password_confirmation'); i.type=i.type==='password'?'text':'password';">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="square" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between pt-4">
                    <a href="{{ route('login') }}" class="font-mono text-sm text-gray-400 transition-colors hover:text-[#00ff88] hover:underline" wire:navigate>
                        {{ __('Already registered?') }}
                    </a>

                    <button type="submit" class="group relative">
                        <div class="transform -skew-x-12 bg-[#00ff88] px-10 py-3 transition-all duration-200 group-hover:translate-y-0 group-hover:bg-[#33ff99] group-hover:shadow-[0_0_20px_rgba(0,255,136,0.6)] group-active:translate-y-0.5">
                            <span class="block skew-x-12 text-base font-bold tracking-wider text-black">{{ __('Register') }}</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
