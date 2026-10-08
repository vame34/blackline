@php
    $supported = (array) config('locales.supported', ['en']);
    $current = app()->getLocale();
@endphp

<details class="relative group">
    <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-white/10 bg-[#121212] px-3 py-2 text-xs font-semibold uppercase tracking-wider text-white hover:bg-[#1A1A1A]">
        <span>{{ __('ui.locales.'.$current) }}</span>
        <svg class="h-3 w-3 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </summary>

    <div class="absolute right-0 z-20 mt-2 w-36 overflow-hidden rounded-xl border border-white/10 bg-[#0E0E0E] shadow-2xl">
        <div class="p-1">
            @foreach ($supported as $locale)
                <a
                    href="{{ route('locale.switch', ['locale' => $locale]) }}"
                    class="block rounded-lg px-3 py-2 text-sm text-slate-200 hover:bg-white/10"
                >
                    <span>{{ __('ui.locales.'.$locale) }}</span>
                </a>
            @endforeach
        </div>
    </div>
</details>
