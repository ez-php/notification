<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Notification\Channel\DatabaseChannel;
use EzPhp\Notification\Channel\DatabaseNotificationRepository;
use EzPhp\Notification\Channel\ToDatabaseInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;
use PDO;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\NotificationPdoDatabase;
use Tests\TestCase;

/**
 * Notifiable fixture for the repository test.
 */
final class RepositoryTestUser implements NotifiableInterface
{
    public function __construct(private readonly int $id)
    {
    }

    public function routeNotificationFor(string $channel): string|int
    {
        return $channel === 'database' ? $this->id : '';
    }
}

/**
 * Database notification fixture.
 */
final class RepositoryTestNotification implements NotificationInterface, ToDatabaseInterface
{
    public function __construct(private readonly string $text)
    {
    }

    /**
     * @return list<string>
     */
    public function via(): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(NotifiableInterface $notifiable): array
    {
        return ['text' => $this->text];
    }
}

/**
 * Class DatabaseNotificationRepositoryTest
 *
 * @package Tests\Channel
 * @uses \Tests\Support\NotificationPdoDatabase
 */
#[CoversClass(DatabaseNotificationRepository::class)]
#[UsesClass(DatabaseChannel::class)]
final class DatabaseNotificationRepositoryTest extends TestCase
{
    private DatabaseChannel $channel;

    private DatabaseNotificationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->channel = new DatabaseChannel($pdo);
        $this->repository = new DatabaseNotificationRepository(new NotificationPdoDatabase($pdo));
    }

    private function notify(RepositoryTestUser $user, string $text): void
    {
        $this->channel->send($user, new RepositoryTestNotification($text));
    }

    public function test_unread_for_returns_the_recipients_unread_notifications_newest_first(): void
    {
        $alice = new RepositoryTestUser(1);
        $this->notify($alice, 'first');
        $this->notify($alice, 'second');
        $this->notify(new RepositoryTestUser(2), 'for bob');

        $unread = $this->repository->unreadFor($alice);

        self::assertCount(2, $unread);
        self::assertSame(['text' => 'second'], $unread[0]['data']);
        self::assertSame(['text' => 'first'], $unread[1]['data']);
        self::assertSame(RepositoryTestNotification::class, $unread[0]['type']);
        self::assertGreaterThan(0, $unread[0]['id']);
    }

    public function test_unread_for_honours_the_limit(): void
    {
        $alice = new RepositoryTestUser(1);
        $this->notify($alice, 'a');
        $this->notify($alice, 'b');

        self::assertCount(1, $this->repository->unreadFor($alice, limit: 1));
    }

    public function test_mark_as_read_and_unread_count(): void
    {
        $alice = new RepositoryTestUser(1);
        $this->notify($alice, 'a');
        $this->notify($alice, 'b');
        $id = $this->repository->unreadFor($alice)[0]['id'];

        self::assertSame(2, $this->repository->unreadCount($alice));
        self::assertTrue($this->repository->markAsRead($alice, $id));
        self::assertSame(1, $this->repository->unreadCount($alice));
        self::assertFalse($this->repository->markAsRead($alice, $id), 'already read');
    }

    public function test_mark_as_read_cannot_touch_another_recipients_notification(): void
    {
        $alice = new RepositoryTestUser(1);
        $bob = new RepositoryTestUser(2);
        $this->notify($bob, 'private');
        $bobsId = $this->repository->unreadFor($bob)[0]['id'];

        self::assertFalse($this->repository->markAsRead($alice, $bobsId));
        self::assertSame(1, $this->repository->unreadCount($bob));
    }

    public function test_mark_all_as_read_only_for_the_recipient(): void
    {
        $alice = new RepositoryTestUser(1);
        $bob = new RepositoryTestUser(2);
        $this->notify($alice, 'a');
        $this->notify($alice, 'b');
        $this->notify($bob, 'c');

        self::assertSame(2, $this->repository->markAllAsRead($alice));
        self::assertSame(0, $this->repository->unreadCount($alice));
        self::assertSame(1, $this->repository->unreadCount($bob));
        self::assertSame([], $this->repository->unreadFor($alice));
    }

    public function test_nothing_sent_yet_means_nothing_unread(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $repository = new DatabaseNotificationRepository(new NotificationPdoDatabase($pdo));
        $pdo->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, type TEXT, notifiable_type TEXT, notifiable_id TEXT, data TEXT, read_at TEXT NULL, created_at TEXT)');

        self::assertSame(0, $repository->unreadCount(new RepositoryTestUser(1)));
        self::assertSame(0, $repository->markAllAsRead(new RepositoryTestUser(1)));
    }
}
