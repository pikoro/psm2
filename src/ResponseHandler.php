<?php

declare(strict_types=1);

namespace App;

use App\Theme\ThemeManager;
use App\Translation\TranslationManager;

final class ResponseHandler
{
    private string $template;
    private array $data = [];

    public function __construct(string $template)
    {
        $this->template = $template;
    }

    public function with(string $key, mixed $value): self
    {
        $this->data[$key] = $value;
        return $this;
    }

    public function withMany(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function render(): void
    {
        // Load user's language preference
        $loggedInUser = Auth::getUser();
        $userLanguage = Auth::isLoggedIn() && $loggedInUser !== null
            ? ($loggedInUser['language'] ?? 'en')
            : ($_COOKIE['language'] ?? 'en');
        TranslationManager::setLocale($userLanguage);

        $twig = self::getTwig();

        $this->data['user_id']      = Auth::getId();
        $this->data['username']     = Auth::getUsername();
        $this->data['is_logged_in'] = Auth::isLoggedIn();
        $this->data['is_admin']     = Auth::isAdmin();
        $this->data['csrf_token']   = CSRF::renderField();
        $this->data['flash']        = [
            'error'   => Session::flash('error'),
            'success' => Session::flash('success'),
            'info'    => Session::flash('info'),
            'warning' => Session::flash('warning'),
        ];
        $this->data['app_name']     = $_ENV['APP_NAME'] ?? 'PHPSwapMeet 2';
        $this->data['current_locale'] = TranslationManager::getLocale();
        $this->data['supported_locales'] = TranslationManager::getSupportedLocales();

        foreach ($this->data['flash'] as $k => $v) {
            if ($v === null) {
                unset($this->data['flash'][$k]);
            }
        }

        // Inject theme data
        $theme = new ThemeManager();
        $this->data['theme_name']         = $theme->getActive();
        $this->data['theme_config']       = $theme->getConfig();
        $this->data['theme_logo_svg']     = $theme->getLogoSvg();
        $this->data['theme_stylesheet_url'] = $theme->getStylesheetUrl();
        $this->data['theme_has_custom_header'] = $theme->hasCustomHeader();
        $this->data['theme_has_custom_footer'] = $theme->hasCustomFooter();
        $this->data['theme_custom_header'] = $theme->hasCustomHeader() ? $theme->renderCustomHeader($this->data) : '';
        $this->data['theme_custom_footer'] = $theme->hasCustomFooter() ? $theme->renderCustomFooter($this->data) : '';
        $this->data['available_themes']   = $theme->getAvailable();
        $this->data['current_route']      = $_SERVER['REQUEST_URI'] ?? '/';

        // Categories for theme nav bars
        $this->data['categories'] = \App\Models\Category::getAll();
        $this->data['active_category'] = (int) ($_GET['cat'] ?? 0);

        echo $twig->render($this->template, $this->data);
    }

    private static function getTwig(): \Twig\Environment
    {
        static $twig = null;
        if ($twig === null) {
            $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
            $twig = new \Twig\Environment($loader, [
                'cache' => false,
                'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
            ]);
            // Register translation extension
            $twig->addExtension(new \App\Twig\TranslationExtension());
        }
        return $twig;
    }
}
