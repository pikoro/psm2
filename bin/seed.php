#!/usr/bin/env php
<?php

/**
 * Seed data script
 * Populates the database with sample data and generates real password hashes
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

echo "Connecting to MySQL...\n";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname}", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Read seed SQL
    $seedSql = file_get_contents(__DIR__ . '/../config/seed.sql');
    if ($seedSql === false) {
        throw new RuntimeException("Failed to read seed.sql");
    }

    // Generate real password hashes for all users
    $password = $_ENV['SEED_PASSWORD'] ?? 'password123';
    $hashes = [];
    for ($i = 0; $i < 5; $i++) {
        $hashes[$i] = password_hash($password, PASSWORD_ARGON2ID);
    }

    // Replace placeholders with real hashes
    $seedSql = str_replace('PLACEHOLDER_HASH_ADMIN', $hashes[0], $seedSql);
    $seedSql = str_replace('PLACEHOLDER_HASH_1', $hashes[1], $seedSql);
    $seedSql = str_replace('PLACEHOLDER_HASH_2', $hashes[2], $seedSql);
    $seedSql = str_replace('PLACEHOLDER_HASH_3', $hashes[3], $seedSql);
    $seedSql = str_replace('PLACEHOLDER_HASH_4', $hashes[4], $seedSql);

    // Split and execute
    $statements = array_filter(array_map('trim', explode(';', $seedSql)));
    $count = 0;
    foreach ($statements as $stmt) {
        if ($stmt === '' || strpos($stmt, '--') === 0) {
            continue;
        }
        try {
            $pdo->exec($stmt);
            $count++;
        } catch (PDOException $e) {
            // Skip duplicate entry errors
            if ($e->getCode() != 23000) {
                echo "Warning: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Seeding complete. {$count} statements executed.\n";
    echo "All user passwords set to: {$password}\n";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (RuntimeException $e) {
    echo $e->getMessage() . "\n";
    exit(1);
}
