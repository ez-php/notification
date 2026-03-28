<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Mail\Mail;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendMailNotificationJob;
use EzPhp\Queue\Job;

/**
 * Class MailChannel
 *
 * Delivers notifications via ez-php/mail by calling Mail::send() with the
 * Mailable returned by the notification's toMail() method.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendMailNotificationJob that carries the pre-built Mailable, avoiding the
 * need to re-invoke toMail() inside the Worker.
 *
 * Requires: MailServiceProvider must be registered in the application.
 *
 * @package EzPhp\Notification\Channel
 */
final class MailChannel implements QueuableChannelInterface
{
    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!$notification instanceof ToMailInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the mail channel.',
                $notification::class,
                ToMailInterface::class,
            ));
        }

        Mail::send($notification->toMail($notifiable));
    }

    /**
     * {@inheritDoc}
     */
    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job
    {
        if (!$notification instanceof ToMailInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the mail channel.',
                $notification::class,
                ToMailInterface::class,
            ));
        }

        return new SendMailNotificationJob($notification->toMail($notifiable));
    }
}
