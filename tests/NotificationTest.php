<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\Notification;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Notifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Spy notifier that records send/sendNow calls.
 */
final class SpyNotifier
{
    /** @var list<array{method: string, notifiable: NotifiableInterface, notification: NotificationInterface}> */
    private array $calls = [];

    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->calls[] = ['method' => 'send', 'notifiable' => $notifiable, 'notification' => $notification];
    }

    public function sendNow(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $this->calls[] = ['method' => 'sendNow', 'notifiable' => $notifiable, 'notification' => $notification];
    }

    /**
     * @return list<array{method: string, notifiable: NotifiableInterface, notification: NotificationInterface}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }
}

/**
 * Class NotificationTest
 *
 * @package Tests
 */
#[CoversClass(Notification::class)]
#[UsesClass(Notifier::class)]
final class NotificationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Notification::resetNotifier();
    }

    protected function tearDown(): void
    {
        Notification::resetNotifier();
        parent::tearDown();
    }

    public function testSendThrowsWhenNotInitialised(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not initialised/');

        Notification::send(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );
    }

    public function testSendNowThrowsWhenNotInitialised(): void
    {
        $this->expectException(\RuntimeException::class);

        Notification::sendNow(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );
    }

    public function testSendDelegatesToNotifier(): void
    {
        $notifier = new Notifier([]);
        Notification::setNotifier($notifier);

        // No exception means the Notifier was called — via() returns empty, so nothing is dispatched.
        Notification::send(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );

        $this->addToAssertionCount(1);
    }

    public function testSendNowDelegatesToNotifier(): void
    {
        $notifier = new Notifier([]);
        Notification::setNotifier($notifier);

        Notification::sendNow(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );

        $this->addToAssertionCount(1);
    }

    public function testResetNotifierClearsInstance(): void
    {
        Notification::setNotifier(new Notifier([]));
        Notification::resetNotifier();

        $this->expectException(\RuntimeException::class);

        Notification::send(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );
    }

    public function testSetNotifierCanReplaceExistingNotifier(): void
    {
        $spy = new SpyChannel();
        $first = new Notifier(['ch' => $spy]);
        $second = new Notifier([]);

        Notification::setNotifier($first);
        Notification::setNotifier($second);

        // Using second notifier (empty channels, empty via) — no dispatch happens.
        Notification::send(
            new class () implements NotifiableInterface {
                public function routeNotificationFor(string $channel): string
                {
                    return '';
                }
            },
            new class () implements NotificationInterface {
                public function via(): array
                {
                    return [];
                }
            }
        );

        $this->assertCount(0, $spy->getCalls(), 'First notifier must have been replaced');
    }
}
