<?php

declare(strict_types=1);

namespace App\Twig;

use App\Translation\TranslationManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Twig extension providing translation filters.
 */
final class TranslationExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('trans', [$this, 'translate']),
            new TwigFilter('trans_plural', [$this, 'translatePlural']),
        ];
    }

    /**
     * Translate a string using the TranslationManager.
     * Usage in templates: {{ 'auth.login' | trans }}
     */
    public function translate(string $key, ?string $locale = null): string
    {
        return TranslationManager::get($key, $locale);
    }

    /**
     * Translate with pluralization.
     * Usage: {{ 'item' | trans_plural('items', count) }}
     */
    public function translatePlural(string $singular, string $plural, int $count, ?string $locale = null): string
    {
        return TranslationManager::plural($singular, $plural, $count, $locale);
    }
}
