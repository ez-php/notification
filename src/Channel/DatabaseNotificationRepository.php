<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Notification\NotifiableInterface;

/**
 * Class DatabaseNotificationRepository
 *
 * Read-state helpers for notifications stored by DatabaseChannel: list and
 * count a recipient's unread notifications, mark one or all as read. The
 * recipient is identified the way DatabaseChannel stores it — its class and
 * `routeNotificationFor('database')` — and every query is scoped to it, so a
 * caller can never read or mark another recipient's notifications by id.
 *
 * Expects the `notifications` table DatabaseChannel creates (or the README's DDL).
 *
 * @package EzPhp\Notification\Channel
 */
final readonly class DatabaseNotificationRepository
{
    /**
     * DatabaseNotificationRepository Constructor
     *
     * @param DatabaseInterface $db
     */
    public function __construct(private DatabaseInterface $db)
    {
    }

    /**
     * Unread notifications of the recipient, newest first.
     *
     * @param NotifiableInterface $notifiable
     * @param int                 $limit
     *
     * @return list<array{id: int, type: string, data: array<string, mixed>, created_at: string}>
     */
    public function unreadFor(NotifiableInterface $notifiable, int $limit = 50): array
    {
        $rows = $this->db->query(
            'SELECT id, type, data, created_at FROM notifications'
            . ' WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL'
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . max(1, $limit),
            $this->owner($notifiable),
        );

        $notifications = [];

        foreach ($rows as $row) {
            $decoded = json_decode(is_string($row['data'] ?? null) ? $row['data'] : '[]', true);
            /** @var array<string, mixed> $data */
            $data = is_array($decoded) ? $decoded : [];

            $notifications[] = [
                'id' => is_numeric($row['id'] ?? null) ? (int) $row['id'] : 0,
                'type' => is_string($row['type'] ?? null) ? $row['type'] : '',
                'data' => $data,
                'created_at' => is_string($row['created_at'] ?? null) ? $row['created_at'] : '',
            ];
        }

        return $notifications;
    }

    /**
     * @param NotifiableInterface $notifiable
     *
     * @return int
     */
    public function unreadCount(NotifiableInterface $notifiable): int
    {
        $rows = $this->db->query(
            'SELECT COUNT(*) AS unread FROM notifications'
            . ' WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL',
            $this->owner($notifiable),
        );

        $count = $rows[0]['unread'] ?? 0;

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * Mark one of the recipient's notifications as read.
     *
     * @param NotifiableInterface $notifiable
     * @param int                 $id
     *
     * @return bool False when it does not exist, belongs to someone else, or was already read.
     */
    public function markAsRead(NotifiableInterface $notifiable, int $id): bool
    {
        return $this->db->execute(
            'UPDATE notifications SET read_at = ?'
            . ' WHERE id = ? AND notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), $id, ...$this->owner($notifiable)],
        ) > 0;
    }

    /**
     * @param NotifiableInterface $notifiable
     *
     * @return int Number of notifications marked.
     */
    public function markAllAsRead(NotifiableInterface $notifiable): int
    {
        return $this->db->execute(
            'UPDATE notifications SET read_at = ?'
            . ' WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL',
            [date('Y-m-d H:i:s'), ...$this->owner($notifiable)],
        );
    }

    /**
     * @param NotifiableInterface $notifiable
     *
     * @return array{0: string, 1: string}
     */
    private function owner(NotifiableInterface $notifiable): array
    {
        return [$notifiable::class, (string) $notifiable->routeNotificationFor('database')];
    }
}
