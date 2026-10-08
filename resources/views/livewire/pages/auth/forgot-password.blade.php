<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth-terminal')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
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

    <main class="relative z-10 flex w-full max-w-[440px] flex-col items-center gap-12 px-4 py-24">
        <div class="neon-border-glow relative w-full border border-white/10 bg-white/[0.02] p-8 backdrop-blur-sm transition-all duration-300">
            <div class="absolute -left-[1px] -top-[1px] h-4 w-4 border-l-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -right-[1px] -top-[1px] h-4 w-4 border-r-2 border-t-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -left-[1px] h-4 w-4 border-b-2 border-l-2 border-[#00ff88]"></div>
            <div class="absolute -bottom-[1px] -right-[1px] h-4 w-4 border-b-2 border-r-2 border-[#00ff88]"></div>

            <div class="mb-8 flex items-center">
                <div class="mr-3 h-4 w-1 bg-[#00ff88] shadow-[0_0_8px_#00ff88]"></div>
                <h2 class="text-xs uppercase tracking-[0.15em] text-gray-400">{{ __('custom.login.forgot') }}</h2>
            </div>

            <p class="mb-6 font-mono text-sm text-gray-400">
                {{ __('custom.login.forgot_subtitle') }}
            </p>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form wire:submit="sendPasswordResetLink" class="space-y-6">
                <div class="group space-y-2">
                    <label for="email" class="block text-xs uppercase tracking-[0.12em] text-gray-400 transition-colors group-focus-within:text-[#00ff88]">{{ __('custom.login.email') }}</label>
                    <input
                        wire:model="email"
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        class="w-full rounded-none border border-white/10 bg-[#0a0a0a] px-4 py-3.5 font-mono text-base text-white outline-none transition-all placeholder:text-gray-600 focus:border-[#00ff88] focus:shadow-[0_0_10px_rgba(0,255,136,0.15)]"
                        placeholder="user@example.com"
                    />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('login') }}" class="font-mono text-sm text-gray-400 transition-colors hover:text-[#00ff88] hover:underline" wire:navigate>
                        {{ __('custom.login.back_to_login') }}
                    </a>

                    <button type="submit" class="group relative">
                        <div class="transform -skew-x-12 bg-[#00ff88] px-8 py-3 transition-all duration-200 group-hover:translate-y-0 group-hover:bg-[#33ff99] group-hover:shadow-[0_0_20px_rgba(0,255,136,0.6)] group-active:translate-y-0.5">
                            <span class="block skew-x-12 text-sm font-bold tracking-wider text-black">{{ __('custom.login.send_reset_link') }}</span>
                        </div>
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>
