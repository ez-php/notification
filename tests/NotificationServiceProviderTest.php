<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Notification\Channel\BroadcastChannel;
use EzPhp\Notification\Channel\MailChannel;
use EzPhp\Notification\Channel\PushChannel;
use EzPhp\Notification\Channel\SmsChannel;
use EzPhp\Notification\Channel\ToMailInterface;
use EzPhp\Notification\Channel\ToSmsInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\Notification;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\NotificationServiceProvider;
use EzPhp\Notification\Notifier;
use EzPhp\Sms\Driver\ArrayDriver as SmsArrayDriver;
use EzPhp\Sms\SmsMessage;
use EzPhp\Sms\SmsSenderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;
use Tests\Support\NotificationSpyMailer;

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
#[UsesClass(PushChannel::class)]
#[UsesClass(SmsChannel::class)]
#[UsesClass(NotificationException::class)]
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

    /**
     * The mail channel delivers through the container's MailerInterface — no
     * Mail::setMailer() needed when a mailer is bound.
     */
    public function test_mail_channel_uses_the_bound_mailer(): void
    {
        $mailer = new NotificationSpyMailer();
        $container = new FakeContainer(new FakeConfig([]));
        $container->instance(MailerInterface::class, $mailer);
        (new NotificationServiceProvider($container))->register();

        $mailable = (new Mailable())->to('user@example.com')->subject('Bound');
        $notification = new class ($mailable) implements NotificationInterface, ToMailInterface {
            public function __construct(private readonly Mailable $mailable)
            {
            }

            public function via(): array
            {
                return ['mail'];
            }

            public function toMail(NotifiableInterface $notifiable): Mailable
            {
                return $this->mailable;
            }
        };
        $notifiable = new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel): string
            {
                return 'user@example.com';
            }
        };

        $notifier = $container->make(Notifier::class);
        $this->assertInstanceOf(Notifier::class, $notifier);
        $notifier->sendNow($notifiable, $notification);

        $this->assertSame([$mailable], $mailer->getSent());
    }

    public function test_sms_channel_is_registered_when_a_sender_is_bound(): void
    {
        $sender = new SmsArrayDriver();
        $container = new FakeContainer(new FakeConfig([]));
        $container->instance(SmsSenderInterface::class, $sender);
        (new NotificationServiceProvider($container))->register();

        $container->make(Notifier::class)->sendNow($this->smsNotifiable(), $this->smsNotification());

        $this->assertCount(1, $sender->sentTo('+4366412345678'));
    }

    public function test_sms_channel_is_absent_without_a_sender_binding(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        (new NotificationServiceProvider($container))->register();

        $this->expectException(NotificationException::class);

        $container->make(Notifier::class)->sendNow($this->smsNotifiable(), $this->smsNotification());
    }

    public function test_boot_initialises_notification_facade(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new NotificationServiceProvider($container);

        $provider->register();
        $provider->boot(); // sets the Notification facade without throwing

        $this->assertInstanceOf(Notifier::class, $container->make(Notifier::class));
    }

    private function smsNotifiable(): NotifiableInterface
    {
        return new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel): string
            {
                return '+4366412345678';
            }
        };
    }

    private function smsNotification(): NotificationInterface
    {
        return new class () implements NotificationInterface, ToSmsInterface {
            public function via(): array
            {
                return ['sms'];
            }

            public function smsTo(NotifiableInterface $notifiable): string
            {
                return (string) $notifiable->routeNotificationFor('sms');
            }

            public function toSms(NotifiableInterface $notifiable): SmsMessage
            {
                return new SmsMessage('Your order has shipped.');
            }
        };
    }
}
