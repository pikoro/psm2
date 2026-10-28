<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Item
{
    public static function findById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT i.*, u.username AS owner_username, u.id AS owner_id,
                    c.name AS category_name
             FROM items i
             JOIN users u ON i.owner_id = u.id
             LEFT JOIN categories c ON i.category_id = c.id
             WHERE i.id = ? AND i.is_active = 1 AND i.status = 'active'
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $item = $stmt->fetch();
        return $item ?: null;
    }

    public static function create(array $data, int $ownerId): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO items (owner_id, category_id, name, description, post_type, price, currency, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'active')"
        );
        $stmt->execute([
            $ownerId,
            $data['category_id'] ?? null,
            $data['name'],
            $data['description'],
            $data['post_type'] ?? 'offer',
            $data['price'] ?? null,
            $data['currency'] ?? 'USD',
        ]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowed = ['category_id', 'name', 'description', 'post_type', 'price', 'currency', 'status'];
        foreach ($allowed as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $values[] = $id;
        $sql = "UPDATE items SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($values);

        return $stmt->rowCount() > 0;
    }

    public static function deactivate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE items SET is_active = 0, status = 'inactive' WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function activate(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE items SET is_active = 1, status = 'active' WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function addPhoto(int $itemId, string $fileName, int $displayOrder = 0): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO item_photos (item_id, file_name, display_order) VALUES (?, ?, ?)"
        );
        $stmt->execute([$itemId, $fileName, $displayOrder]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function getPhotos(int $itemId): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM item_photos WHERE item_id = ? ORDER BY display_order ASC"
        );
        $stmt->execute([$itemId]);
        return $stmt->fetchAll();
    }

    public static function getAll(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $where = ['i.is_active = 1', "i.status = 'active'"];
        $params = [];

        if (!empty($filters['category_id'])) {
            $where[] = 'i.category_id = ?';
            $params[] = $filters['category_id'];
        }
        if (!empty($filters['post_type'])) {
            $where[] = 'i.post_type = ?';
            $params[] = $filters['post_type'];
        }
        if (!empty($filters['owner_id'])) {
            $where[] = 'i.owner_id = ?';
            $params[] = $filters['owner_id'];
        }

        $whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT i.*, u.username AS owner_username, u.id AS owner_id,
                       c.name AS category_name
                FROM items i
                JOIN users u ON i.owner_id = u.id
                LEFT JOIN categories c ON i.category_id = c.id
                {$whereClause}
                ORDER BY i.created_at DESC
                LIMIT ? OFFSET ?";

        $params[] = $perPage;
        $params[] = $offset;

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll();

        $countSql = "SELECT COUNT(*) FROM items i {$whereClause}";
        $stmt = Database::getConnection()->prepare($countSql);
        $stmt->execute(array_slice($params, 0, -2));
        $total = (int) $stmt->fetchColumn();

        return ['items' => $items, 'total' => $total];
    }

    public static function search(string $keyword, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);
        $search = "%{$keyword}%";

        $stmt = Database::getConnection()->prepare(
            "SELECT i.*, u.username AS owner_username, u.id AS owner_id,
                    c.name AS category_name
             FROM items i
             JOIN users u ON i.owner_id = u.id
             LEFT JOIN categories c ON i.category_id = c.id
             WHERE i.is_active = 1 AND i.status = 'active'
             AND (i.name LIKE ? OR i.description LIKE ?)
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$search, $search, $perPage, $offset]);
        $items = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM items
             WHERE is_active = 1 AND status = 'active'
             AND (name LIKE ? OR description LIKE ?)"
        );
        $stmt->execute([$search, $search]);
        $total = (int) $stmt->fetchColumn();

        return ['items' => $items, 'total' => $total];
    }

    public static function getOwnerItems(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $stmt = Database::getConnection()->prepare(
            "SELECT i.*, c.name AS category_name
             FROM items i
             LEFT JOIN categories c ON i.category_id = c.id
             WHERE i.owner_id = ? AND i.is_active = 1 AND i.status = 'active'
             ORDER BY i.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $perPage, $offset]);
        $items = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM items WHERE owner_id = ? AND is_active = 1 AND status = 'active'"
        );
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        return ['items' => $items, 'total' => $total];
    }

    // Admin helpers
    public static function getCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM items");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getActiveCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM items WHERE is_active = 1 AND status = 'active'");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    public static function getRecentItems(int $limit = 10): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT i.*, u.username AS owner_username
             FROM items i
             JOIN users u ON i.owner_id = u.id
             WHERE i.is_active = 1 AND i.status = 'active'
             ORDER BY i.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
}
