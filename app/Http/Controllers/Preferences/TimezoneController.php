<?php

namespace App\Http\Controllers\Preferences;

use App\Services\Preferences\DisplayTimezone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class TimezoneController
{
    public function __invoke(Request $request, DisplayTimezone $timezones)
    {
        $data = $request->validate([
            'timezone' => ['required', 'string', Rule::in(['auto', ...$timezones->identifiers()])],
            'browser_timezone' => ['nullable', 'string', Rule::in($timezones->identifiers())],
        ]);
        $request->session()->put('display_timezone.preference', $data['timezone']);
        if (! empty($data['browser_timezone'])) {
            $request->session()->put('display_timezone.browser', $data['browser_timezone']);
        }
        $request->user()?->forceFill(['timezone' => $data['timezone']])->save();

        return $request->expectsJson() ? response()->noContent() : redirect()->back();
    }
}
