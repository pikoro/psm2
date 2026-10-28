<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Feedback
{
    public static function create(int $fromUserId, int $toUserId, ?int $itemId, int $rating, ?string $comment = null): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO feedback (from_user_id, to_user_id, item_id, rating, comment) VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$fromUserId, $toUserId, $itemId, $rating, $comment]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function getByUserId(int $userId, int $limit = 50): array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT f.*, u.username AS from_username
             FROM feedback f
             JOIN users u ON f.from_user_id = u.id
             WHERE f.to_user_id = ?
             ORDER BY f.created_at DESC
             LIMIT ?"
        );
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll();
    }

    public static function getByItemAndUsers(int $fromUserId, int $toUserId): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT * FROM feedback WHERE from_user_id = ? AND to_user_id = ? LIMIT 1"
        );
        $stmt->execute([$fromUserId, $toUserId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function getAverage(int $userId): float
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT AVG(rating) AS avg_rating FROM feedback WHERE to_user_id = ?"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return (float) ($row['avg_rating'] ?? 0);
    }

    public static function getTotalCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM feedback");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
