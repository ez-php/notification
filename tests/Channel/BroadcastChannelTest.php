<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Broadcast\Driver\ArrayDriver;
use EzPhp\Notification\Channel\BroadcastChannel;
use EzPhp\Notification\Channel\ToBroadcastInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Queue\SendBroadcastNotificationJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class BroadcastChannelTest
 *
 * @package Tests\Channel
 */
#[CoversClass(BroadcastChannel::class)]
#[UsesClass(SendBroadcastNotificationJob::class)]
#[UsesClass(\EzPhp\Notification\NotificationException::class)]
final class BroadcastChannelTest extends TestCase
{
    private ArrayDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new ArrayDriver();
        Broadcast::setBroadcaster(new Broadcaster($this->driver));
    }

    protected function tearDown(): void
    {
        Broadcast::resetBroadcaster();
        parent::tearDown();
    }

    private function makeNotifiable(): NotifiableInterface
    {
        return new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel): int
            {
                return 42;
            }
        };
    }

    private function makeBroadcastNotification(): NotificationInterface&ToBroadcastInterface
    {
        return new class () implements NotificationInterface, ToBroadcastInterface {
            public function via(): array
            {
                return ['broadcast'];
            }

            public function broadcastOn(NotifiableInterface $notifiable): string
            {
                return 'users.42';
            }

            public function broadcastAs(NotifiableInterface $notifiable): string
            {
                return 'notification.created';
            }

            /** @return array<string, mixed> */
            public function broadcastWith(NotifiableInterface $notifiable): array
            {
                return ['msg' => 'hello'];
            }
        };
    }

    public function testSendPublishesToBroadcastChannel(): void
    {
        $channel = new BroadcastChannel();
        $channel->send($this->makeNotifiable(), $this->makeBroadcastNotification());

        $events = $this->driver->eventsOn('users.42');
        $this->assertCount(1, $events);
        $this->assertSame('notification.created', $events[0]['event']);
        $this->assertSame(['msg' => 'hello'], $events[0]['payload']);
    }

    public function testSendThrowsWhenNotificationDoesNotImplementToBroadcastInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['broadcast'];
            }
        };

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessageMatches('/ToBroadcastInterface/');

        $channel = new BroadcastChannel();
        $channel->send($this->makeNotifiable(), $notification);
    }

    public function testToJobReturnsBroadcastJob(): void
    {
        $channel = new BroadcastChannel();
        $job = $channel->toJob($this->makeNotifiable(), $this->makeBroadcastNotification());

        $this->assertInstanceOf(SendBroadcastNotificationJob::class, $job);
    }

    public function testToJobThrowsWhenNotificationDoesNotImplementToBroadcastInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['broadcast'];
            }
        };

        $this->expectException(NotificationException::class);

        $channel = new BroadcastChannel();
        $channel->toJob($this->makeNotifiable(), $notification);
    }

    public function testSendBroadcastJobPublishesEvent(): void
    {
        $job = new SendBroadcastNotificationJob('users.42', 'notification.created', ['msg' => 'hello']);
        $job->handle();

        $events = $this->driver->eventsOn('users.42');
        $this->assertCount(1, $events);
        $this->assertSame('notification.created', $events[0]['event']);
    }
}
