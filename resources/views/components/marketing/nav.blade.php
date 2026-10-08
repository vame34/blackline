<nav class="fixed left-0 top-0 z-50 w-full border-b border-white/10 bg-black/95 text-white backdrop-blur">
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4 px-6 py-5">
        <a href="{{ route('home') }}" class="text-2xl font-black italic tracking-tight" wire:navigate>BlackLine</a>
        <div class="flex flex-wrap items-center gap-5 text-sm font-mono">
            <a href="{{ route('signals.index') }}" class="hover:text-[#00ff88]" wire:navigate>{{ __('ui.nav.signals') }}</a>
            <a href="{{ route('performance.terminal') }}" class="hover:text-[#00ff88]" wire:navigate>{{ __('ui.nav.performance') }}</a>
            <a href="{{ route('api.docs') }}" class="hover:text-[#00ff88]" wire:navigate>API</a>
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="hover:text-[#00ff88]">{{ __('ui.nav.logout') }}</button>
                </form>
                @if(auth()->user()->hasRole('admin'))
                    <a href="{{ url('/admin') }}" class="hover:text-[#00ff88]">{{ __('ui.nav.admin') }}</a>
                @endif
            @else
                <a href="{{ route('login') }}" class="text-[#00ff88]" wire:navigate>{{ __('custom.login.submit') }}</a>
            @endauth
            <x-timezone-selector />
            <a href="{{ route('locale.switch', ['locale' => app()->getLocale() === 'ru' ? 'en' : 'ru']) }}">{{ app()->getLocale() === 'ru' ? 'EN' : 'RU' }}</a>
        </div>
    </div>
</nav>
