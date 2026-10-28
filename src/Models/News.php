<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class News
{
    public static function getAll(): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM news ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getActiveArticles(): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM news WHERE is_active = 1 ORDER BY created_at DESC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM news WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO news (title, content, is_active) VALUES (?, ?, ?)"
        );
        $stmt->execute([$data['title'], $data['content'], $data['is_active'] ?? 1]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['title'])) {
            $fields[] = 'title = ?';
            $values[] = $data['title'];
        }
        if (isset($data['content'])) {
            $fields[] = 'content = ?';
            $values[] = $data['content'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $values[] = $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE news SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    public static function toggleActive(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE news SET is_active = NOT is_active WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function delete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM news WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
