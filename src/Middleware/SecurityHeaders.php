<?php

declare(strict_types=1);

namespace App\Middleware;

final class SecurityHeaders
{
    public function handle(): bool
    {
        // Content-Security-Policy
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

        // X-Frame-Options
        header('X-Frame-Options: DENY');

        // X-Content-Type-Options
        header('X-Content-Type-Options: nosniff');

        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Permissions-Policy
        header("Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()");

        // Cache control for all pages
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        return true;
    }
}
