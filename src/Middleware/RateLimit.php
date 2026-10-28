<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Response;
use App\Session;
use App\Database;

final class RateLimit
{
    private int $maxAttempts;
    private int $windowSeconds;

    public function __construct(int $maxAttempts = 5, int $windowSeconds = 300)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
    }

    public function handle(): bool
    {
        // Skip rate limiting when APP_DEBUG is true for easier local testing
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            return true;
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $action = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $identifier = 'ip:' . $ip;

        $conn = Database::getConnection();

        // Clean old entries
        $conn->prepare(
            "DELETE FROM rate_limits WHERE last_attempt < DATE_SUB(NOW(), INTERVAL ? SECOND)"
        )->execute([$this->windowSeconds]);

        // Check current count
        $stmt = $conn->prepare(
            "SELECT attempts FROM rate_limits WHERE identifier = ? AND action = ? AND last_attempt > DATE_SUB(NOW(), INTERVAL ? SECOND)"
        );
        $stmt->execute([$identifier, $action, $this->windowSeconds]);
        $row = $stmt->fetch();

        if ($row !== false && $row['attempts'] >= $this->maxAttempts) {
            Session::flash('error', 'Too many requests. Please try again later.');
            Response::redirect('/');
            return false;
        }

        // Increment or insert
        if ($row === false) {
            $conn->prepare(
                "INSERT INTO rate_limits (identifier, action, attempts, last_attempt) VALUES (?, ?, 1, NOW())"
            )->execute([$identifier, $action]);
        } else {
            $conn->prepare(
                "UPDATE rate_limits SET attempts = attempts + 1, last_attempt = NOW() WHERE identifier = ? AND action = ?"
            )->execute([$identifier, $action]);
        }

        return true;
    }
}
