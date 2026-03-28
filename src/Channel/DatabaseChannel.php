<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use PDO;

/**
 * Class DatabaseChannel
 *
 * Persists notifications to the `notifications` database table.
 *
 * The table is created automatically on first use via CREATE TABLE IF NOT
 * EXISTS. The DDL adapts to MySQL and SQLite. In production, create the table
 * via your migration system using the schema documented in the README.
 *
 * Does NOT implement QueuableChannelInterface — database writes are fast and
 * do not benefit from queueing. When a ShouldQueueInterface notification lists
 * 'database' as a channel, this channel is always executed synchronously, even
 * if other channels are queued.
 *
 * Requires: DatabaseInterface (DatabaseServiceProvider) must be registered.
 *
 * @package EzPhp\Notification\Channel
 */
final class DatabaseChannel implements ChannelInterface
{
    /**
     * Whether the notifications table has been verified to exist.
     */
    private bool $tableEnsured = false;

    /**
     * DatabaseChannel Constructor
     *
     * @param PDO $pdo
     */
    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!$notification instanceof ToDatabaseInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the database channel.',
                $notification::class,
                ToDatabaseInterface::class,
            ));
        }

        $this->ensureTable();

        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (type, notifiable_type, notifiable_id, data, created_at) ' .
            'VALUES (?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $notification::class,
            $notifiable::class,
            (string) $notifiable->routeNotificationFor('database'),
            json_encode($notification->toDatabase($notifiable), JSON_THROW_ON_ERROR),
            date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create the notifications table if it does not yet exist.
     *
     * DDL is driver-aware: SQLite uses INTEGER PRIMARY KEY AUTOINCREMENT and
     * TEXT columns; MySQL uses INT UNSIGNED AUTO_INCREMENT and native JSON.
     *
     * @return void
     */
    private function ensureTable(): void
    {
        if ($this->tableEnsured) {
            return;
        }

        $driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS notifications (
                    id         INTEGER PRIMARY KEY AUTOINCREMENT,
                    type            TEXT    NOT NULL,
                    notifiable_type TEXT    NOT NULL,
                    notifiable_id   TEXT    NOT NULL,
                    data            TEXT    NOT NULL,
                    read_at         TEXT    NULL,
                    created_at      TEXT    NOT NULL
                )
            ');
        } else {
            $this->pdo->exec('
                CREATE TABLE IF NOT EXISTS notifications (
                    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    type            VARCHAR(255) NOT NULL,
                    notifiable_type VARCHAR(255) NOT NULL,
                    notifiable_id   VARCHAR(255) NOT NULL,
                    data            JSON         NOT NULL,
                    read_at         DATETIME     NULL,
                    created_at      DATETIME     NOT NULL
                )
            ');
        }

        $this->tableEnsured = true;
    }
}
