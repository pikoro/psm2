<?php

declare(strict_types=1);

namespace App;

final class Response
{
    public static function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public static function html(string $content, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . self::sanitizeRedirect($url), true, $status);
        exit;
    }

    public static function download(string $filename, string $mimeType, string $content): void
    {
        http_response_code(200);
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $content;
    }

    private static function sanitizeRedirect(string $url): string
    {
        if (str_starts_with($url, '//')) {
            $url = '/' . ltrim($url, '/');
        }
        if (filter_var($url, FILTER_VALIDATE_URL) !== false && parse_url($url, PHP_URL_HOST) !== null) {
            $url = '/';
        }
        return $url;
    }
}
