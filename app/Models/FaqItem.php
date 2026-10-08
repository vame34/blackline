<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FaqItem extends Model
{
    protected $fillable = [
        'question_translations',
        'answer_translations',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'question_translations' => 'array',
        'answer_translations' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function questionForLocale(?string $locale = null): string
    {
        return $this->translationFor($this->question_translations, $locale);
    }

    public function answerForLocale(?string $locale = null): string
    {
        return $this->translationFor($this->answer_translations, $locale);
    }

    /**
     * @param  array<string, string>|null  $translations
     */
    private function translationFor(?array $translations, ?string $locale = null): string
    {
        $translations = $translations ?? [];
        $locale = $locale ?: app()->getLocale();
        $fallback = (string) config('locales.fallback', config('app.fallback_locale', 'en'));
        $default = (string) config('locales.default', config('app.locale', 'en'));

        foreach ([$locale, $fallback, $default] as $candidate) {
            $value = trim((string) ($translations[$candidate] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        foreach ($translations as $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }
}
