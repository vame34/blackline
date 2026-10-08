@php($timezones = app(\App\Services\Preferences\DisplayTimezone::class))
<form method="POST" action="{{ route('preferences.timezone') }}" data-timezone-form class="flex items-center gap-2">
    @csrf
    <input type="hidden" name="browser_timezone" value="UTC">
    <label for="display-timezone" class="sr-only">{{ __('ui.timezone.label') }}</label>
    <select id="display-timezone" name="timezone" aria-label="{{ __('ui.timezone.label') }}" class="max-w-[180px] border border-white/10 bg-black px-2 py-1 text-xs text-gray-300 focus:border-[#00ff88]" onchange="this.form.requestSubmit()">
        <option value="auto" @selected($timezones->selected() === 'auto')>{{ __('ui.timezone.automatic') }}</option>
        <option value="UTC" @selected($timezones->selected() === 'UTC')>UTC</option>
        @foreach($timezones->groups() as $region => $options)
            <optgroup label="{{ $region }}">
                @foreach($options as $value => $label)
                    <option value="{{ $value }}" @selected($timezones->selected() === $value)>{{ $label }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <noscript><button type="submit">{{ __('ui.timezone.apply') }}</button></noscript>
</form>
