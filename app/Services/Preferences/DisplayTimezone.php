<?php

namespace App\Services\Preferences;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Http\Request;

final class DisplayTimezone
{
    public function __construct(private readonly Request $request) {}

    public function selected(): string
    {
        $value = $this->request->user()?->timezone
            ?? ($this->request->hasSession() ? $this->request->session()->get('display_timezone.preference') : null)
            ?? 'auto';

        return $value === 'auto' || $this->valid($value) ? $value : 'auto';
    }

    public function resolved(): string
    {
        $selected = $this->selected();
        if ($selected !== 'auto') {
            return $selected;
        }
        $browser = $this->request->hasSession() ? $this->request->session()->get('display_timezone.browser', 'UTC') : 'UTC';

        return $this->valid($browser) ? $browser : 'UTC';
    }

    public function identifiers(): array
    {
        return DateTimeZone::listIdentifiers(DateTimeZone::ALL_WITH_BC);
    }

    public function valid(mixed $value): bool
    {
        return is_string($value) && in_array($value, $this->identifiers(), true);
    }

    public function groups(): array
    {
        $groups = [];
        foreach (DateTimeZone::listIdentifiers() as $timezone) {
            if ($timezone === 'UTC') {
                continue;
            }
            $groups[explode('/', $timezone)[0]][$timezone] = str_replace('_', ' ', $timezone);
        }

        return $groups;
    }

    public function format(?DateTimeInterface $date, string $format = 'd.m.Y H:i'): string
    {
        return $date ? CarbonImmutable::instance($date)->setTimezone($this->resolved())->format($format) : '—';
    }
}
