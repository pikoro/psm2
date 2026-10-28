<?php

declare(strict_types=1);

namespace App;

final class CSRF
{
    public static function start(): void
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
    }

    public static function getToken(): string
    {
        return $_SESSION['csrf_token'] ?? '';
    }

    public static function validate(): bool
    {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            return false;
        }
        return true;
    }

    public static function validateOrFail(): void
    {
        if (!self::validate()) {
            http_response_code(403);
            Response::html('<h1>403 — Forbidden</h1><p>Invalid CSRF token. Please try again.</p>', 403);
            exit;
        }
    }

    public static function renderField(): string
    {
        self::start();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getToken(), ENT_QUOTES, 'UTF-8') . '">';
    }
}
