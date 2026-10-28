<?php

declare(strict_types=1);

namespace App\Translation;

/**
 * Manages loading and retrieving translations.
 *
 * Translation files are stored in /translations/{locale}.json
 * Supports: en (English), ja (Japanese)
 */
final class TranslationManager
{
    private const TRANSLATIONS_DIR = __DIR__ . '/../../translations';

    private static array $translations = [];
    private static string $defaultLocale = 'en';
    private static ?string $currentLocale = null;

    /**
     * Get the current locale (falls back to default if not set).
     */
    public static function getLocale(): string
    {
        return self::$currentLocale ?? self::$defaultLocale;
    }

    /**
     * Set the current locale.
     */
    public static function setLocale(string $locale): void
    {
        if (!self::isSupported($locale)) {
            $locale = self::$defaultLocale;
        }
        self::$currentLocale = $locale;
        self::loadTranslations($locale);
    }

    /**
     * Check if a locale is supported.
     */
    public static function isSupported(string $locale): bool
    {
        return file_exists(self::getTranslationFile($locale));
    }

    /**
     * Get list of supported locales.
     */
    public static function getSupportedLocales(): array
    {
        $locales = [];
        if (!is_dir(self::TRANSLATIONS_DIR)) {
            return $locales;
        }
        foreach (scandir(self::TRANSLATIONS_DIR) as $file) {
            if (str_ends_with($file, '.json')) {
                $locale = substr($file, 0, -5);
                $locales[$locale] = self::getLocaleName($locale);
            }
        }
        return $locales;
    }

    /**
     * Get a translated string.
     * Falls back to the key itself if not found.
     */
    public static function get(string $key, ?string $locale = null): string
    {
        $locale = $locale ?? self::getLocale();
        $translations = self::$translations[$locale] ?? [];

        // Try exact match first
        if (isset($translations[$key])) {
            return $translations[$key];
        }

        // Try with locale prefix (e.g., "auth.login")
        $parts = explode('.', $key);
        if (count($parts) > 1) {
            $fileKey = $parts[0];
            $translationKey = implode('.', array_slice($parts, 1));
            if (isset($translations[$fileKey]) && is_array($translations[$fileKey])) {
                $value = $translations[$fileKey];
                foreach (explode('.', $translationKey) as $part) {
                    if (isset($value[$part])) {
                        $value = $value[$part];
                    } else {
                        return $key;
                    }
                }
                return $value;
            }
        }

        return $key;
    }

    /**
     * Get translated string with pluralization support.
     * Usage: trans('item.count', 0) -> 'No items' or 'item.count' => ['No items', '1 item', '%count% items']
     */
    public static function plural(string $singular, string $plural, int $count, ?string $locale = null): string
    {
        $locale = $locale ?? self::getLocale();
        $translations = self::$translations[$locale] ?? [];

        $key = $count === 1 ? $singular : $plural;
        if (isset($translations[$key])) {
            $value = $translations[$key];
            if (is_array($value)) {
                return $value[$count] ?? ($count === 1 ? $translations[$singular] : $translations[$plural]);
            }
            return $value;
        }

        // Fallback: simple pluralization
        if ($count === 0) {
            return '0 ' . strtolower(explode('.', $plural)[count(explode('.', $plural)) - 1]);
        }
        return $count . ' ' . $plural;
    }

    /**
     * Load translations for a specific locale.
     */
    private static function loadTranslations(string $locale): void
    {
        $file = self::getTranslationFile($locale);
        if (!file_exists($file)) {
            self::$translations[$locale] = [];
            return;
        }

        $content = file_get_contents($file);
        self::$translations[$locale] = json_decode($content, true) ?: [];
    }

    /**
     * Get the translation file path for a locale.
     */
    private static function getTranslationFile(string $locale): string
    {
        return self::TRANSLATIONS_DIR . '/' . $locale . '.json';
    }

    /**
     * Get human-readable locale name.
     */
    private static function getLocaleName(string $locale): string
    {
        $names = [
            'en' => 'English',
            'ja' => '日本語',
        ];
        return $names[$locale] ?? $locale;
    }
}
