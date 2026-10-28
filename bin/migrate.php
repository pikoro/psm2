#!/usr/bin/env php
<?php

/**
 * Database migration script
 * Creates the database and runs the schema
 */

// Load environment variables — try composer autoload first, fall back to manual .env loader
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
    $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
} else {
    // Manual .env loader for when composer install hasn't been run yet
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath)) {
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            if (strpos($line, '=') === false) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, "\"' ");
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'phpswapmeet';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

echo "Connecting to MySQL at {$host}...\n";

try {
    $pdo = new PDO("mysql:host={$host}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbname}' ready.\n";

    $pdo->exec("USE `{$dbname}`");

    // Read and execute schema
    $schema = file_get_contents(__DIR__ . '/../config/schema.sql');
    if ($schema === false) {
        throw new RuntimeException("Failed to read schema.sql");
    }

    // Split by semicolons and execute each statement
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        $pdo->exec($stmt);
        $count++;
    }

    echo "Migration complete. {$count} statements executed.\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (RuntimeException $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
