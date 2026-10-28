<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Category
{
    public static function getAll(): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM categories WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function getAllWithCount(): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT c.*, COUNT(i.id) AS item_count
             FROM categories c
             LEFT JOIN items i ON c.id = i.category_id AND i.is_active = 1 AND i.status = 'active'
             WHERE c.is_active = 1
             GROUP BY c.id
             ORDER BY c.sort_order ASC, c.name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM categories WHERE id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $cat = $stmt->fetch();
        return $cat ?: null;
    }

    public static function create(array $data): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO categories (name, description, parent_id, sort_order) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['name'],
            $data['description'] ?? null,
            $data['parent_id'] ?? null,
            $data['sort_order'] ?? 0,
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        if (isset($data['name'])) {
            $fields[] = 'name = ?';
            $values[] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = ?';
            $values[] = $data['description'];
        }
        if (isset($data['parent_id'])) {
            $fields[] = 'parent_id = ?';
            $values[] = $data['parent_id'] ?: null;
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = ?';
            $values[] = $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = ?';
            $values[] = $data['is_active'];
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    public static function deactivate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE categories SET is_active = 0 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function activate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE categories SET is_active = 1 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function toggleActive(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE categories SET is_active = NOT is_active WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
