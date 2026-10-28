<?php

declare(strict_types=1);

namespace App\Models;

use App\Database;

final class Message
{
    public static function create(int $fromUserId, int $toUserId, string $subject, string $body): int
    {
        $stmt = Database::getConnection()->prepare(
            "INSERT INTO messages (from_user_id, to_user_id, subject, body) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$fromUserId, $toUserId, $subject, $body]);
        return (int) Database::getConnection()->lastInsertId();
    }

    public static function getInbox(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, s.username AS sender_name, r.username AS receiver_name
             FROM messages m
             JOIN users s ON m.from_user_id = s.id
             JOIN users r ON m.to_user_id = r.id
             WHERE m.to_user_id = ? AND m.deleted_by_receiver = 0
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $perPage, $offset]);
        $messages = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND deleted_by_receiver = 0"
        );
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        return ['messages' => $messages, 'total' => $total];
    }

    public static function getSent(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, s.username AS sender_name, r.username AS receiver_name
             FROM messages m
             JOIN users s ON m.from_user_id = s.id
             JOIN users r ON m.to_user_id = r.id
             WHERE m.from_user_id = ? AND m.deleted_by_sender = 0
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $perPage, $offset]);
        $messages = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM messages WHERE from_user_id = ? AND deleted_by_sender = 0"
        );
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        return ['messages' => $messages, 'total' => $total];
    }

    public static function getTrash(int $userId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $perPage = min($perPage, 100);

        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, s.username AS sender_name, r.username AS receiver_name
             FROM messages m
             JOIN users s ON m.from_user_id = s.id
             JOIN users r ON m.to_user_id = r.id
             WHERE (m.deleted_by_sender = 1 OR m.deleted_by_receiver = 1)
               AND m.to_user_id = ?
             ORDER BY m.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $perPage, $offset]);
        $messages = $stmt->fetchAll();

        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM messages
             WHERE (deleted_by_sender = 1 OR deleted_by_receiver = 1)
               AND to_user_id = ?"
        );
        $stmt->execute([$userId]);
        $total = (int) $stmt->fetchColumn();

        return ['messages' => $messages, 'total' => $total];
    }

    public static function getById(int $id): ?array
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT m.*, s.username AS sender_name, r.username AS receiver_name
             FROM messages m
             JOIN users s ON m.from_user_id = s.id
             JOIN users r ON m.to_user_id = r.id
             WHERE m.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $msg = $stmt->fetch();
        return $msg ?: null;
    }

    public static function markAsRead(int $id): bool
    {
        $stmt = Database::getConnection()->prepare(
            "UPDATE messages SET is_read = 1 WHERE id = ? AND is_read = 0"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function moveToTrash(int $id, int $userId, string $role): bool
    {
        $column = $role === 'sender' ? 'deleted_by_sender' : 'deleted_by_receiver';
        $stmt = Database::getConnection()->prepare(
            "UPDATE messages SET {$column} = 1 WHERE id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function permanentlyDelete(int $id): bool
    {
        $stmt = Database::getConnection()->prepare("DELETE FROM messages WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function getUnreadCount(int $userId): int
    {
        $stmt = Database::getConnection()->prepare(
            "SELECT COUNT(*) FROM messages WHERE to_user_id = ? AND is_read = 0 AND deleted_by_receiver = 0"
        );
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }

    public static function getTotalCount(): int
    {
        $stmt = Database::getConnection()->prepare("SELECT COUNT(*) FROM messages");
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }
}
