<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendSmsNotificationJob;
use EzPhp\Queue\Job;
use EzPhp\Sms\SmsSenderInterface;

/**
 * Class SmsChannel
 *
 * Delivers notifications via ez-php/sms: the phone number and SmsMessage
 * returned by the notification's smsTo() and toSms() methods go to the
 * injected SmsSenderInterface.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendSmsNotificationJob that carries the pre-resolved number/message,
 * avoiding re-invocation inside the Worker.
 *
 * Requires ez-php/sms (a suggest). NotificationServiceProvider registers
 * this channel only when SmsSenderInterface is bound (SmsServiceProvider).
 * The queued SendSmsNotificationJob uses the Sms façade, which
 * SmsServiceProvider also wires.
 *
 * @package EzPhp\Notification\Channel
 */
final class SmsChannel implements QueuableChannelInterface
{
    /**
     * @param SmsSenderInterface $sender Delivers the SMS.
     */
    public function __construct(private readonly SmsSenderInterface $sender)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $notification = $this->assertSupported($notification);

        $this->sender->send($notification->smsTo($notifiable), $notification->toSms($notifiable));
    }

    /**
     * {@inheritDoc}
     */
    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job
    {
        $notification = $this->assertSupported($notification);

        return new SendSmsNotificationJob(
            $notification->smsTo($notifiable),
            $notification->toSms($notifiable),
        );
    }

    /**
     * @param NotificationInterface $notification
     *
     * @throws NotificationException When the notification does not implement ToSmsInterface.
     *
     * @return ToSmsInterface
     */
    private function assertSupported(NotificationInterface $notification): ToSmsInterface
    {
        if (!$notification instanceof ToSmsInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the sms channel.',
                $notification::class,
                ToSmsInterface::class,
            ));
        }

        return $notification;
    }
}
