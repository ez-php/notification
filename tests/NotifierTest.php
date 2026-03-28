<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\QueueInterface;
use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Notifier;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\ShouldQueueInterface;
use EzPhp\Queue\Job;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Spy channel that records every send() call.
 */
final class SpyChannel implements ChannelInterface
{
    /** @var list<array{notifiable: NotifiableInterface, notification: NotificationInterface}> */
    private array $calls = [];

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->calls[] = ['notifiable' => $notifiable, 'notification' => $notification];
    }

    /**
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}

/**
 * Spy channel that also implements QueuableChannelInterface.
 */
final class QueuableSpyChannel implements QueuableChannelInterface
{
    /** @var list<array{notifiable: NotifiableInterface, notification: NotificationInterface}> */
    private array $sends = [];

    /** @var list<Job> */
    private array $jobs = [];

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->sends[] = ['notifiable' => $notifiable, 'notification' => $notification];
    }

    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job
    {
        $job = new class () extends Job {
            public function handle(): void
            {
            }
        };
        $this->jobs[] = $job;

        return $job;
    }

    /**
     * @return list<array{notifiable: NotifiableInterface, notification: NotificationInterface}>
     */
    public function getSends(): array
    {
        return $this->sends;
    }

    /**
     * @return list<Job>
     */
    public function getJobs(): array
    {
        return $this->jobs;
    }
}

/**
 * Spy queue driver.
 */
final class SpyQueue implements QueueInterface
{
    /** @var list<Job> */
    private array $pushed = [];

    public function push(\EzPhp\Contracts\JobInterface $job): void
    {
        if ($job instanceof Job) {
            $this->pushed[] = $job;
        }
    }

    public function pop(string $queue = 'default'): ?\EzPhp\Contracts\JobInterface
    {
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    public function failed(\EzPhp\Contracts\JobInterface $job, \Throwable $exception): void
    {
    }

    /**
     * @return list<Job>
     */
    public function getPushed(): array
    {
        return $this->pushed;
    }
}

/**
 * Simple notifiable stub.
 */
final class StubNotifiable implements NotifiableInterface
{
    public function routeNotificationFor(string $channel): string
    {
        return 'user@example.com';
    }
}

/**
 * Simple notification sending via one channel.
 */
final class OneChannelNotification implements NotificationInterface
{
    public function __construct(private readonly string $channelName)
    {
    }

    public function via(): array
    {
        return [$this->channelName];
    }
}

/**
 * Queueable notification.
 */
final class QueuedNotification implements NotificationInterface, ShouldQueueInterface
{
    public function via(): array
    {
        return ['queuable'];
    }
}

/**
 * Class NotifierTest
 *
 * @package Tests
 */
#[CoversClass(Notifier::class)]
#[UsesClass(\EzPhp\Notification\NotificationException::class)]
final class NotifierTest extends TestCase
{
    public function testSendNowDispatchesToChannel(): void
    {
        $spy = new SpyChannel();
        $notifier = new Notifier(['alpha' => $spy]);
        $notifiable = new StubNotifiable();
        $notification = new OneChannelNotification('alpha');

        $notifier->sendNow($notifiable, $notification);

        $calls = $spy->getCalls();
        $this->assertCount(1, $calls);
        $this->assertSame($notifiable, $calls[0]['notifiable']);
        $this->assertSame($notification, $calls[0]['notification']);
    }

    public function testSendDispatchesSynchronouslyWithoutQueue(): void
    {
        $spy = new SpyChannel();
        $notifier = new Notifier(['alpha' => $spy]);

        $notifier->send(new StubNotifiable(), new OneChannelNotification('alpha'));

        $this->assertCount(1, $spy->getCalls());
    }

    public function testSendDispatchesToMultipleChannels(): void
    {
        $spyA = new SpyChannel();
        $spyB = new SpyChannel();
        $notifier = new Notifier(['a' => $spyA, 'b' => $spyB]);

        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['a', 'b'];
            }
        };

        $notifier->sendNow(new StubNotifiable(), $notification);

        $this->assertCount(1, $spyA->getCalls());
        $this->assertCount(1, $spyB->getCalls());
    }

    public function testSendThrowsForUnknownChannel(): void
    {
        $notifier = new Notifier([]);

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessageMatches("/Unknown notification channel: 'missing'/");

        $notifier->sendNow(new StubNotifiable(), new OneChannelNotification('missing'));
    }

    public function testShouldQueuePushesJobForQueuableChannel(): void
    {
        $spy = new QueuableSpyChannel();
        $queue = new SpyQueue();
        $notifier = new Notifier(['queuable' => $spy], $queue);

        $notifier->send(new StubNotifiable(), new QueuedNotification());

        $this->assertCount(0, $spy->getSends(), 'send() should not be called for queuable channel');
        $this->assertCount(1, $spy->getJobs(), 'toJob() should be called once');
        $this->assertCount(1, $queue->getPushed(), 'Job should be pushed to queue');
    }

    public function testShouldQueueRunsSyncForNonQueuableChannel(): void
    {
        $spy = new SpyChannel(); // does NOT implement QueuableChannelInterface
        $queue = new SpyQueue();

        $notification = new class () implements NotificationInterface, ShouldQueueInterface {
            public function via(): array
            {
                return ['plain'];
            }
        };

        $notifier = new Notifier(['plain' => $spy], $queue);
        $notifier->send(new StubNotifiable(), $notification);

        $this->assertCount(1, $spy->getCalls(), 'Non-queuable channel should be executed synchronously');
        $this->assertCount(0, $queue->getPushed(), 'No Job should be pushed for non-queuable channel');
    }

    public function testShouldQueueFallsBackToSyncWhenNoQueueAvailable(): void
    {
        $spy = new QueuableSpyChannel();
        $notifier = new Notifier(['queuable' => $spy], null);

        $notifier->send(new StubNotifiable(), new QueuedNotification());

        $this->assertCount(1, $spy->getSends(), 'Should fall back to sync when queue is null');
        $this->assertCount(0, $spy->getJobs());
    }

    public function testSendNowBypassesQueueEvenForShouldQueueNotification(): void
    {
        $spy = new QueuableSpyChannel();
        $queue = new SpyQueue();
        $notifier = new Notifier(['queuable' => $spy], $queue);

        $notifier->sendNow(new StubNotifiable(), new QueuedNotification());

        $this->assertCount(1, $spy->getSends(), 'sendNow() must always call send() directly');
        $this->assertCount(0, $queue->getPushed(), 'sendNow() must never push to queue');
    }
}
