<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Notification\Channel\BroadcastChannel;
use EzPhp\Notification\Channel\MailChannel;
use EzPhp\Notification\Notification;
use EzPhp\Notification\NotificationServiceProvider;
use EzPhp\Notification\Notifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: NotificationServiceProvider registers and boots its bindings in a
 * minimal container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(NotificationServiceProvider::class)]
#[UsesClass(Notifier::class)]
#[UsesClass(MailChannel::class)]
#[UsesClass(BroadcastChannel::class)]
#[UsesClass(Notification::class)]
final class NotificationServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Notification::resetNotifier();
        parent::tearDown();
    }

    public function test_register_binds_notifier(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new NotificationServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->wasBound(Notifier::class));
        // With no DB/queue bound, the notifier is built with mail + broadcast channels.
        $this->assertInstanceOf(Notifier::class, $container->make(Notifier::class));
    }

    public function test_boot_initialises_notification_facade(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new NotificationServiceProvider($container);

        $provider->register();
        $provider->boot(); // sets the Notification facade without throwing

        $this->assertInstanceOf(Notifier::class, $container->make(Notifier::class));
    }
}
