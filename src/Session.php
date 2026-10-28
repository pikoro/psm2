<?php

declare(strict_types=1);

namespace App;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = ($_ENV['SESSION_SECURE'] ?? 'false') === 'true';
        $lifetime = (int) ($_ENV['SESSION_LIFETIME'] ?? '120');

        session_name('PSM2SESSID');
        session_set_cookie_params([
            'lifetime' => $lifetime * 60,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        if (!isset($_SESSION['csrf_token'])) {
            self::regenerate();
        } elseif (time() - ($_SESSION['last_regenerate'] ?? 0) > 900) {
            self::regenerate();
        }
    }

    public static function regenerate(): void
    {
        $old = $_SESSION ?? [];
        session_regenerate_id(true);
        $_SESSION = $old;
        $_SESSION['last_regenerate'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function flash(string $key, mixed $value = null): mixed
    {
        if ($value === null) {
            $flash = $_SESSION['flash'][$key] ?? null;
            unset($_SESSION['flash'][$key]);
            return $flash;
        }
        $_SESSION['flash'][$key] = $value;
        return null;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function destroy(): void
    {
        session_destroy();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
    }
}
