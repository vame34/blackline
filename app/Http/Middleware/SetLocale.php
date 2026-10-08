<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = (array) config('locales.supported', ['en']);
        $supported = array_values(array_filter(array_map('strval', $supported)));
        $fallback = (string) config('locales.fallback', 'en');

        $locale = null;

        if ($request->hasSession()) {
            $candidate = (string) $request->session()->get('locale', '');
            if ($candidate !== '') {
                $locale = $candidate;
            }
        }

        if (!$locale && $request->user() && is_string($request->user()->locale ?? null)) {
            $candidate = (string) $request->user()->locale;
            if ($candidate !== '') {
                $locale = $candidate;
            }
        }

        if (!$locale) {
            $header = (string) $request->header('Accept-Language', '');
            $best = $this->resolveFromAcceptLanguage($header, $supported);
            if ($best) {
                $locale = $best;
            }
        }

        $locale = $this->normalizeLocale($locale);
        if ($locale && in_array($locale, $supported, true)) {
            app()->setLocale($locale);
        } else {
            app()->setLocale($fallback);
        }

        return $next($request);
    }

    /**
     * @param array<int, string> $supported
     */
    private function resolveFromAcceptLanguage(string $header, array $supported): ?string
    {
        if (trim($header) === '') {
            return null;
        }

        foreach (explode(',', $header) as $part) {
            $lang = trim(explode(';', $part)[0] ?? '');
            $lang = $this->normalizeLocale($lang);
            if (!$lang) {
                continue;
            }
            if (in_array($lang, $supported, true)) {
                return $lang;
            }
        }

        return null;
    }

    private function normalizeLocale(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $raw = Str::lower(str_replace('_', '-', $raw));
        $primary = explode('-', $raw)[0] ?? $raw;
        $primary = trim($primary);

        return $primary !== '' ? $primary : null;
    }
}

