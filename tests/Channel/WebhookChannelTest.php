<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Notification\Channel\ToWebhookInterface;
use EzPhp\Notification\Channel\WebhookChannel;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Webhook\Job\DeliverWebhookJob;
use EzPhp\Webhook\WebhookDispatcher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;
use Throwable;

/**
 * Queue that records pushed jobs.
 */
final class WebhookChannelRecordingQueue implements QueueInterface
{
    /** @var list<JobInterface> */
    public array $pushed = [];

    public function push(JobInterface $job): void
    {
        $this->pushed[] = $job;
    }

    public function pop(string $queue = 'default'): ?JobInterface
    {
        return null;
    }

    public function size(string $queue = 'default'): int
    {
        return count($this->pushed);
    }

    public function failed(JobInterface $job, Throwable $exception): void
    {
    }
}

/**
 * Notifiable fixture.
 */
final class WebhookChannelCustomer implements NotifiableInterface
{
    public function routeNotificationFor(string $channel): string|int
    {
        return $channel === 'webhook' ? 'https://hooks.example.com/orders' : 0;
    }
}

/**
 * Webhook notification fixture.
 */
final class WebhookChannelOrderShipped implements NotificationInterface, ToWebhookInterface
{
    /**
     * @return list<string>
     */
    public function via(): array
    {
        return ['webhook'];
    }

    public function webhookUrl(NotifiableInterface $notifiable): string
    {
        return (string) $notifiable->routeNotificationFor('webhook');
    }

    public function webhookSecret(NotifiableInterface $notifiable): string
    {
        return 's3cret';
    }

    /**
     * @return array<string, mixed>
     */
    public function toWebhook(NotifiableInterface $notifiable): array
    {
        return ['event' => 'order.shipped', 'order_id' => 7];
    }
}

/**
 * Class WebhookChannelTest
 *
 * @package Tests\Channel
 */
#[CoversClass(WebhookChannel::class)]
#[UsesClass(WebhookDispatcher::class)]
#[UsesClass(DeliverWebhookJob::class)]
#[UsesClass(NotificationException::class)]
final class WebhookChannelTest extends TestCase
{
    public function test_dispatches_a_signed_delivery_job(): void
    {
        $queue = new WebhookChannelRecordingQueue();

        (new WebhookChannel(new WebhookDispatcher($queue)))->send(new WebhookChannelCustomer(), new WebhookChannelOrderShipped());

        self::assertCount(1, $queue->pushed);
        $job = $queue->pushed[0];
        self::assertInstanceOf(DeliverWebhookJob::class, $job);
        self::assertSame('https://hooks.example.com/orders', $this->property($job, 'url'));
        self::assertSame(['event' => 'order.shipped', 'order_id' => 7], $this->property($job, 'payload'));
        self::assertSame('s3cret', $this->property($job, 'secret'));
    }

    public function test_rejects_notifications_without_to_webhook(): void
    {
        $notification = new class () implements NotificationInterface {
            /**
             * @return list<string>
             */
            public function via(): array
            {
                return ['webhook'];
            }
        };

        $this->expectException(NotificationException::class);

        (new WebhookChannel(new WebhookDispatcher(new WebhookChannelRecordingQueue())))->send(new WebhookChannelCustomer(), $notification);
    }

    private function property(object $object, string $name): mixed
    {
        return (new \ReflectionProperty($object, $name))->getValue($object);
    }
}
