<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Notification\Channel\SmsChannel;
use EzPhp\Notification\Channel\ToSmsInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Queue\SendSmsNotificationJob;
use EzPhp\Sms\Driver\ArrayDriver;
use EzPhp\Sms\Sms;
use EzPhp\Sms\SmsMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Class SmsChannelTest
 *
 * @package Tests\Channel
 */
#[CoversClass(SmsChannel::class)]
#[CoversClass(SendSmsNotificationJob::class)]
#[UsesClass(NotificationException::class)]
final class SmsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Sms::resetSender();
        parent::tearDown();
    }

    private function makeNotifiable(): NotifiableInterface
    {
        return new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel): string
            {
                return $channel === 'sms' ? '+4366412345678' : '';
            }
        };
    }

    private function makeNotification(SmsMessage $message): NotificationInterface
    {
        return new class ($message) implements NotificationInterface, ToSmsInterface {
            public function __construct(private readonly SmsMessage $message)
            {
            }

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
                return $this->message;
            }
        };
    }

    public function testSendDeliversThroughInjectedSender(): void
    {
        $driver = new ArrayDriver();
        $message = new SmsMessage('Shipped');

        new SmsChannel($driver)->send($this->makeNotifiable(), $this->makeNotification($message));

        $this->assertSame([$message], $driver->sentTo('+4366412345678'));
    }

    public function testSendThrowsWhenNotificationLacksToSmsInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['sms'];
            }
        };

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessage('to use the sms channel');

        new SmsChannel(new ArrayDriver())->send($this->makeNotifiable(), $notification);
    }

    public function testToJobPreResolvesAndHandleDeliversViaFacade(): void
    {
        $facadeDriver = new ArrayDriver();
        Sms::setSender($facadeDriver);
        $injected = new ArrayDriver();
        $message = new SmsMessage('Queued');

        $job = new SmsChannel($injected)->toJob($this->makeNotifiable(), $this->makeNotification($message));

        $this->assertInstanceOf(SendSmsNotificationJob::class, $job);

        $job->handle();

        $this->assertSame([$message], $facadeDriver->sentTo('+4366412345678'));
        $this->assertSame([], $injected->sentTo('+4366412345678'));
    }

    public function testToJobThrowsWhenNotificationLacksToSmsInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['sms'];
            }
        };

        $this->expectException(NotificationException::class);

        new SmsChannel(new ArrayDriver())->toJob($this->makeNotifiable(), $notification);
    }
}
