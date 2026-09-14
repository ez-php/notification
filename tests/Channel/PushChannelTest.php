<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Notification\Channel\PushChannel;
use EzPhp\Notification\Channel\ToPushInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Queue\SendPushNotificationJob;
use EzPhp\Push\Driver\ArrayDriver;
use EzPhp\Push\Push;
use EzPhp\Push\Pusher;
use EzPhp\Push\PushMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class PushChannelTest
 *
 * @package Tests\Channel
 */
#[CoversClass(PushChannel::class)]
#[UsesClass(SendPushNotificationJob::class)]
#[UsesClass(\EzPhp\Notification\NotificationException::class)]
final class PushChannelTest extends TestCase
{
    private ArrayDriver $driver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->driver = new ArrayDriver();
        Push::setPusher(new Pusher($this->driver));
    }

    protected function tearDown(): void
    {
        Push::resetPusher();
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

    private function makePushNotification(): NotificationInterface&ToPushInterface
    {
        return new class () implements NotificationInterface, ToPushInterface {
            public function via(): array
            {
                return ['push'];
            }

            public function pushToken(NotifiableInterface $notifiable): string
            {
                return 'device-token-42';
            }

            public function toPush(NotifiableInterface $notifiable): PushMessage
            {
                return new PushMessage('Hello', 'Welcome!');
            }
        };
    }

    public function testSendDeliversToPushChannel(): void
    {
        $channel = new PushChannel();
        $channel->send($this->makeNotifiable(), $this->makePushNotification());

        $messages = $this->driver->sentTo('device-token-42');
        $this->assertCount(1, $messages);
        $this->assertSame('Hello', $messages[0]->title);
        $this->assertSame('Welcome!', $messages[0]->body);
    }

    public function testSendThrowsWhenNotificationDoesNotImplementToPushInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['push'];
            }
        };

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessageMatches('/ToPushInterface/');

        $channel = new PushChannel();
        $channel->send($this->makeNotifiable(), $notification);
    }

    public function testToJobReturnsPushJob(): void
    {
        $channel = new PushChannel();
        $job = $channel->toJob($this->makeNotifiable(), $this->makePushNotification());

        $this->assertInstanceOf(SendPushNotificationJob::class, $job);
    }

    public function testToJobThrowsWhenNotificationDoesNotImplementToPushInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['push'];
            }
        };

        $this->expectException(NotificationException::class);

        $channel = new PushChannel();
        $channel->toJob($this->makeNotifiable(), $notification);
    }

    public function testSendPushJobDeliversMessage(): void
    {
        $job = new SendPushNotificationJob('device-token-42', new PushMessage('Hi', 'There'));
        $job->handle();

        $messages = $this->driver->sentTo('device-token-42');
        $this->assertCount(1, $messages);
        $this->assertSame('Hi', $messages[0]->title);
    }
}
