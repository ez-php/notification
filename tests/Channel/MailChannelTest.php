<?php

declare(strict_types=1);

namespace Tests\Channel;

use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;
use EzPhp\Notification\Channel\MailChannel;
use EzPhp\Notification\Channel\ToMailInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\Queue\SendMailNotificationJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Spy mailer that captures every sent Mailable.
 */
final class SpyMailer implements MailerInterface
{
    /** @var list<Mailable> */
    private array $sent = [];

    public function send(Mailable $mailable): void
    {
        $this->sent[] = $mailable;
    }

    /**
     * @return list<Mailable>
     */
    public function getSent(): array
    {
        return $this->sent;
    }
}

/**
 * Class MailChannelTest
 *
 * @package Tests\Channel
 */
#[CoversClass(MailChannel::class)]
#[UsesClass(Mailable::class)]
#[UsesClass(Mail::class)]
#[UsesClass(SendMailNotificationJob::class)]
#[UsesClass(\EzPhp\Notification\NotificationException::class)]
final class MailChannelTest extends TestCase
{
    private SpyMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new SpyMailer();
        Mail::setMailer($this->mailer);
    }

    protected function tearDown(): void
    {
        Mail::resetMailer();
        parent::tearDown();
    }

    private function makeNotifiable(): NotifiableInterface
    {
        return new class () implements NotifiableInterface {
            public function routeNotificationFor(string $channel): string
            {
                return 'user@example.com';
            }
        };
    }

    public function testSendDeliversMailable(): void
    {
        $mailable = (new Mailable())->to('user@example.com')->subject('Hello');

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

        $channel = new MailChannel();
        $channel->send($this->makeNotifiable(), $notification);

        $sent = $this->mailer->getSent();
        $this->assertCount(1, $sent);
        $this->assertSame($mailable, $sent[0]);
    }

    public function testSendThrowsWhenNotificationDoesNotImplementToMailInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['mail'];
            }
        };

        $this->expectException(NotificationException::class);
        $this->expectExceptionMessageMatches('/ToMailInterface/');

        $channel = new MailChannel();
        $channel->send($this->makeNotifiable(), $notification);
    }

    public function testToJobReturnsMailJob(): void
    {
        $mailable = (new Mailable())->to('user@example.com')->subject('Hello');

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

        $channel = new MailChannel();
        $job = $channel->toJob($this->makeNotifiable(), $notification);

        $this->assertInstanceOf(SendMailNotificationJob::class, $job);
    }

    public function testToJobThrowsWhenNotificationDoesNotImplementToMailInterface(): void
    {
        $notification = new class () implements NotificationInterface {
            public function via(): array
            {
                return ['mail'];
            }
        };

        $this->expectException(NotificationException::class);

        $channel = new MailChannel();
        $channel->toJob($this->makeNotifiable(), $notification);
    }

    public function testSendMailJobDeliversMailable(): void
    {
        $mailable = (new Mailable())->to('user@example.com')->subject('Test');
        $job = new SendMailNotificationJob($mailable);
        $job->handle();

        $sent = $this->mailer->getSent();
        $this->assertCount(1, $sent);
        $this->assertSame($mailable, $sent[0]);
    }
}
