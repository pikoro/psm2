<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;
use App\Validator;

final class User
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE id = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByUsername(string $username): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE username = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function findByResetToken(string $token): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1"
        );
        $stmt->execute([$token]);
        $user = $stmt->fetch();
        return $user ?: null;
    }

    public static function create(string $username, string $email, string $passwordHash, string $paypalEmail = ''): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO users (username, email, password_hash, paypal_email, language) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$username, $email, $passwordHash, $paypalEmail, 'en']);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['username'])) {
            $fields[] = 'username = ?';
            $values[] = $data['username'];
        }
        if (isset($data['email'])) {
            $fields[] = 'email = ?';
            $values[] = $data['email'];
        }
        if (isset($data['paypal_email'])) {
            $fields[] = 'paypal_email = ?';
            $values[] = $data['paypal_email'];
        }
        if (isset($data['bio'])) {
            $fields[] = 'bio = ?';
            $values[] = $data['bio'];
        }
        if (isset($data['first_name'])) {
            $fields[] = 'first_name = ?';
            $values[] = $data['first_name'];
        }
        if (isset($data['last_name'])) {
            $fields[] = 'last_name = ?';
            $values[] = $data['last_name'];
        }
        if (isset($data['language'])) {
            $fields[] = 'language = ?';
            $values[] = $data['language'];
        }
        if (isset($data['password_hash'])) {
            $fields[] = 'password_hash = ?';
            $values[] = $data['password_hash'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    public static function updatePassword(int $id, string $passwordHash): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET password_hash = ? WHERE id = ?"
        );
        $stmt->execute([$passwordHash, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function setResetToken(int $id, string $token, int $expires): bool
    {
        $date = date('Y-m-d H:i:s', $expires);
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?"
        );
        $stmt->execute([$token, $date, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function clearResetToken(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET reset_token = NULL, reset_expires = NULL WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function deactivate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET is_active = 0 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function activate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET is_active = 1 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function loginRecord(int $id, string $ip): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET last_login = NOW(), ip_address = ? WHERE id = ?"
        );
        $stmt->execute([$ip, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function getAll(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE is_active = 1 ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$perPage, $offset]);
        $users = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM users WHERE is_active = 1"
        );
        $stmt->execute();
        $total = (int) $stmt->fetchColumn();

        return ['items' => $users, 'total' => $total];
    }

    public static function search(string $query, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);
        $search = "%{$query}%";

        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users WHERE is_active = 1 AND (username LIKE ? OR email LIKE ?) ORDER BY username ASC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$search, $search, $perPage, $offset]);
        $users = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM users WHERE is_active = 1 AND (username LIKE ? OR email LIKE ?)"
        );
        $stmt->execute([$search, $search]);
        $total = (int) $stmt->fetchColumn();

        return ['items' => $users, 'total' => $total];
    }

    // Admin helpers
    public static function getCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM users");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getActiveCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getRecentUsers(int $limit = 10): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM users ORDER BY created_at DESC LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function toggleActive(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET is_active = NOT is_active WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function softDelete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE users SET is_active = 0 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function updateProfile(int $id, array $data): bool
    {
        return self::update($id, $data);
    }

    public static function createResetToken(string $email): void
    {
        $user = self::findByEmail($email);
        if ($user !== null) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + 3600;
            self::setResetToken($user['id'], $token, $expires);
        }
    }

    public static function updateLastLogin(int $id): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        self::loginRecord($id, $ip);
    }

    public static function createInitialFolders(int $userId): void
    {
        $conn = Database::getConnection();
        foreach (['Inbox', 'Sent', 'Trash'] as $folderName) {
            $conn->prepare(
                "INSERT INTO folders (user_id, folder_name) VALUES (?, ?)"
            )->execute([$userId, $folderName]);
        }
    }
}