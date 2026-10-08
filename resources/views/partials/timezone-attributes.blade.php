@php($displayTimezone = app(\App\Services\Preferences\DisplayTimezone::class))
data-display-timezone="{{ $displayTimezone->selected() === 'auto' ? '' : $displayTimezone->selected() }}"
data-timezone-preference="{{ $displayTimezone->selected() }}"
data-browser-timezone="{{ session('display_timezone.browser', '') }}"
