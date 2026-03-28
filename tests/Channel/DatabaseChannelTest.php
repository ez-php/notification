<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Notification\Channel\DatabaseChannel;
use EzPhp\Notification\Channel\ToDatabaseInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class DatabaseChannelTest
 *
 * Uses an in-memory SQLite database — no MySQL or Docker required.
 *
 * @package Tests\Channel
 */
#[CoversClass(DatabaseChannel::class)]
#[UsesClass(\EzPhp\Notification\NotificationException::class)]
final class DatabaseChannelTest extends TestCase
{
    private PDO $pdo;

    private DatabaseChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->channel = new DatabaseChannel($this->pdo);
    }

    private function makeNotifiable(string|int $id = 42): NotifiableInterface
    {
        return new class ($id) implements NotifiableInterface {
            public function __construct(private readonly string|int $id)
            {
            }

            public function routeNotificationFor(string $channel): string|int
            {
                return $this->id;
            }
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function makeDatabaseNotification(array $payload = ['message' => 'Hello']): NotificationInterface&ToDatabaseInterface
    {
        return new class ($payload) implements NotificationInterface, ToDatabaseInterface {
            /** @param array<string, mixed> $payload */
            public function __construct(private readonly array $payload)
            {
            }

            public function via(): array
            {
                return ['database'];
            }

            /** @return array<string, mixed> */
            public function toDatabase(NotifiableInterface $notifiable): array
            {
                return $this->payload;
            }
        };
    }

    public function testSendCreatesNotificationsTableAutomatically(): void
    {
        $this->channel->send($this->makeNotifiable(), $this->makeDatabaseNotification());

        $stmt = $this->pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='notifications'");
        assert($stmt instanceof PDOStatement);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('notifications', $row['name']);
    }

    public function testSendInsertsRowIntoNotificationsTable(): void
    {
        $notifiable = $this->makeNotifiable(99);
        $notification = $this->makeDatabaseNotification(['message' => 'Test']);

        $this->channel->send($notifiable, $notification);

        $stmt = $this->pdo->query('SELECT * FROM notifications');
        assert($stmt instanceof PDOStatement);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('99', $row['notifiable_id']);
        $this->assertSame('{"message":"Test"}', $row['data']);
        $this->assertNull($row['read_at']);
        $this->assertNotEmpty($row['created_at']);
    }

    public function testSendStoresNotifiableType(): void
    {
        $notifiable = $this->makeNotifiable();
        $this->channel->send($notifiable, $this->makeDatabaseNotification());

        $stmt = $this->pdo->query('SELECT notifiable_type FROM notifications');
        assert($stmt instanceof PDOStatement);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertIsString($row['notifiable_type']);
        $this->assertStringContainsString('@anonymous', $row['notifiable_type']);
    }

    public function testSendStoresNotificationType(): void
    {
        $notification = $this->makeDatabaseNotification();
        $this->channel->send($this->makeNotifiable(), $notification);

        $stmt = $this->pdo->query('SELECT type FROM notifications');
        assert($stmt instanceof PDOStatement);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertNotEmpty($row['type']);
    }

    public function testSendMultipleNotifications(): void
    {
        $this->channel->send($this->makeNotifiable(1), $this->makeDatabaseNotification(['n' => 1]));
        $this->channel->send($this->makeNotifiable(2), $this->makeDatabaseNotification(['n' => 2]));

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM notifications');
        assert($stmt instanceof PDOStatement);
        $count = (int) $stmt->fetchColumn();

        $this->assertSame(2, $count);
    }

    public function testSendThrowsWhenNotificationDoesNotImplementToDatabaseInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['database'];
            }
        };

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessageMatches('/ToDatabaseInterface/');

        $this->channel->send($this->makeNotifiable(), $notification);
    }

    public function testTableIsOnlyCreatedOnce(): void
    {
        // Send twice — ensureTable() should only execute DDL once.
        $this->channel->send($this->makeNotifiable(), $this->makeDatabaseNotification());
        $this->channel->send($this->makeNotifiable(), $this->makeDatabaseNotification());

        $stmt = $this->pdo->query('SELECT COUNT(*) FROM notifications');
        assert($stmt instanceof PDOStatement);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }
}
