<?php

declare(strict_types=1);

namespace App\Theme;

use App\Session;

/**
 * Manages theme loading, switching, and configuration.
 * Themes are stored in the themes/ directory. Each theme has:
 *   - theme.json (metadata)
 *   - header.html.twig (optional header override)
 *   - footer.html.twig (optional footer override)
 *   - styles.css (theme stylesheet)
 *   - logo.svg (theme logo)
 *   - images/ (theme-specific images)
 */
final class ThemeManager
{
    private string $themeDir;
    private array $availableThemes = [];
    private string $activeTheme;

    public function __construct(?string $themeDir = null)
    {
        $this->themeDir = $themeDir ?? __DIR__ . '/../../themes';
        $this->activeTheme = $_ENV['APP_THEME'] ?? 'default';
        $this->loadAvailableThemes();
    }

    /**
     * Load list of available themes from the themes directory.
     */
    private function loadAvailableThemes(): void
    {
        if (!is_dir($this->themeDir)) {
            return;
        }

        foreach (scandir($this->themeDir) as $dir) {
            if ($dir === '.' || $dir === '..' || !is_dir($this->themeDir . '/' . $dir)) {
                continue;
            }

            $configFile = $this->themeDir . '/' . $dir . '/theme.json';
            if (file_exists($configFile)) {
                $config = json_decode(file_get_contents($configFile), true);
                $this->availableThemes[$dir] = $config ?: [
                    'name' => ucfirst(str_replace('_', ' ', $dir)),
                    'author' => 'Unknown',
                    'version' => '1.0.0',
                    'description' => '',
                    'colors' => [
                        'primary' => '#000000',
                        'secondary' => '#ffffff',
                        'accent' => '#000000',
                        'text' => '#000000',
                        'background' => '#ffffff',
                        'header_bg' => '#333333',
                        'header_text' => '#ffffff',
                    ],
                ];
            } else {
                // Minimal theme without config
                $this->availableThemes[$dir] = [
                    'name' => ucfirst(str_replace('_', ' ', $dir)),
                    'author' => 'Unknown',
                    'version' => '1.0.0',
                    'description' => 'Minimal theme.',
                    'colors' => [
                        'primary' => '#000000',
                        'secondary' => '#ffffff',
                        'accent' => '#000000',
                        'text' => '#000000',
                        'background' => '#ffffff',
                        'header_bg' => '#333333',
                        'header_text' => '#ffffff',
                    ],
                ];
            }
        }
    }

    /**
     * Get the active theme name.
     */
    public function getActive(): string
    {
        // Allow session override for theme switching
        $sessionTheme = Session::get('theme_override');
        if ($sessionTheme !== null && isset($this->availableThemes[$sessionTheme])) {
            return $sessionTheme;
        }
        return $this->activeTheme;
    }

    /**
     * Set the active theme (persisted in session).
     */
    public function setActive(string $themeName): bool
    {
        if (!isset($this->availableThemes[$themeName])) {
            return false;
        }
        $this->activeTheme = $themeName;
        Session::set('theme_override', $themeName);
        return true;
    }

    /**
     * Get all available themes.
     */
    public function getAvailable(): array
    {
        return $this->availableThemes;
    }

    /**
     * Get theme configuration array.
     */
    public function getConfig(): array
    {
        return $this->availableThemes[$this->activeTheme] ?? [
            'name' => 'Default',
            'author' => 'Unknown',
            'version' => '1.0.0',
            'description' => '',
            'colors' => [
                'primary' => '#000000',
                'secondary' => '#ffffff',
                'accent' => '#000000',
                'text' => '#000000',
                'background' => '#ffffff',
                'header_bg' => '#333333',
                'header_text' => '#ffffff',
            ],
        ];
    }

    /**
     * Get theme directory path.
     */
    public function getThemeDir(): string
    {
        return $this->themeDir . '/' . $this->activeTheme;
    }

    /**
     * Get the full path to a theme file.
     */
    public function getPath(string $file): ?string
    {
        $path = $this->getThemeDir() . '/' . $file;
        return file_exists($path) ? $path : null;
    }

    /**
     * Check if a theme file exists.
     */
    public function hasFile(string $file): bool
    {
        return file_exists($this->getThemeDir() . '/' . $file);
    }

    /**
     * Get the URL path to a theme asset.
     */
    public function asset(string $file): string
    {
        return '/themes/' . $this->activeTheme . '/' . ltrim($file, '/');
    }

    /**
     * Get theme color value.
     */
    public function getColor(string $key, string $default = '#000000'): string
    {
        $colors = $this->getConfig()['colors'] ?? [];
        return $colors[$key] ?? $default;
    }

    /**
     * Get the logo SVG content, or null if not found.
     */
    public function getLogoSvg(): ?string
    {
        $path = $this->getPath('logo.svg');
        if ($path === null) {
            return null;
        }
        return file_get_contents($path);
    }

    /**
     * Get the logo URL path.
     */
    public function getLogoUrl(): string
    {
        return $this->asset('logo.svg');
    }

    /**
     * Get the theme stylesheet URL.
     */
    public function getStylesheetUrl(): string
    {
        return $this->asset('styles.css');
    }

    /**
     * Check if the theme has a custom header template.
     */
    public function hasCustomHeader(): bool
    {
        return $this->hasFile('header.html.twig');
    }

    /**
     * Check if the theme has a custom footer template.
     */
    public function hasCustomFooter(): bool
    {
        return $this->hasFile('footer.html.twig');
    }

    /**
     * Render a custom header template using Twig.
     */
    public function renderCustomHeader(array $vars = []): string
    {
        $path = $this->getPath('header.html.twig');
        if ($path === null) {
            return '';
        }

        $loader = new \Twig\Loader\FilesystemLoader(dirname($path));
        $twig = new \Twig\Environment($loader, ['cache' => false, 'debug' => false]);
        $twig->addGlobal('theme_manager', $this);
        $twig->addExtension(new \App\Twig\TranslationExtension());
        return $twig->render(basename($path), $vars);
    }

    /**
     * Render a custom footer template using Twig.
     */
    public function renderCustomFooter(array $vars = []): string
    {
        $path = $this->getPath('footer.html.twig');
        if ($path === null) {
            return '';
        }

        $loader = new \Twig\Loader\FilesystemLoader(dirname($path));
        $twig = new \Twig\Environment($loader, ['cache' => false, 'debug' => false]);
        $twig->addGlobal('theme_manager', $this);
        $twig->addExtension(new \App\Twig\TranslationExtension());
        return $twig->render(basename($path), $vars);
    }
}
