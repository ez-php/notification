<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Notification\Channel\RateLimitedChannel;
use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;
use EzPhp\RateLimiter\ArrayDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Spy channel that records every delivered notification.
 */
final class RateLimitedChannelSpyChannel implements ChannelInterface
{
    /** @var list<NotificationInterface> */
    public array $sent = [];

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->sent[] = $notification;
    }
}

final class RateLimitedChannelFakeNotifiable implements NotifiableInterface
{
    public function __construct(private readonly string $id)
    {
    }

    public function routeNotificationFor(string $channel): string
    {
        return $this->id;
    }
}

final class RateLimitedChannelFakeNotification implements NotificationInterface
{
    public function via(): array
    {
        return [];
    }
}

#[CoversClass(RateLimitedChannel::class)]
final class RateLimitedChannelTest extends TestCase
{
    public function testDeliversWhenUnderLimit(): void
    {
        $spy = new RateLimitedChannelSpyChannel();
        $channel = new RateLimitedChannel(
            $spy,
            new ArrayDriver(),
            maxAttempts: 2,
            decaySeconds: 60,
            keyResolver: fn (NotifiableInterface $n, NotificationInterface $notif): string => 'x',
        );

        $notifiable = new RateLimitedChannelFakeNotifiable('1');
        $notification = new RateLimitedChannelFakeNotification();

        $channel->send($notifiable, $notification);

        $this->assertCount(1, $spy->sent);
    }

    public function testSkipsDeliveryWhenLimitExceeded(): void
    {
        $spy = new RateLimitedChannelSpyChannel();
        $channel = new RateLimitedChannel(
            $spy,
            new ArrayDriver(),
            maxAttempts: 1,
            decaySeconds: 60,
            keyResolver: fn (NotifiableInterface $n, NotificationInterface $notif): string => 'x',
        );

        $notifiable = new RateLimitedChannelFakeNotifiable('1');
        $notification = new RateLimitedChannelFakeNotification();

        $channel->send($notifiable, $notification);
        $channel->send($notifiable, $notification);

        $this->assertCount(1, $spy->sent);
    }

    public function testKeyResolverIsolatesLimitsPerKey(): void
    {
        $spy = new RateLimitedChannelSpyChannel();
        $channel = new RateLimitedChannel(
            $spy,
            new ArrayDriver(),
            maxAttempts: 1,
            decaySeconds: 60,
            keyResolver: fn (NotifiableInterface $n, NotificationInterface $notif): string => $n->routeNotificationFor('x') . '',
        );

        $userA = new RateLimitedChannelFakeNotifiable('a');
        $userB = new RateLimitedChannelFakeNotifiable('b');
        $notification = new RateLimitedChannelFakeNotification();

        $channel->send($userA, $notification);
        $channel->send($userB, $notification);

        $this->assertCount(2, $spy->sent);
    }
}
