<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Mail\Mail;
use EzPhp\Mail\MailerInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendMailNotificationJob;
use EzPhp\Queue\Job;

/**
 * Class MailChannel
 *
 * Delivers notifications via ez-php/mail: the Mailable returned by the
 * notification's toMail() method goes to the injected MailerInterface, or —
 * constructed without one — to the Mail façade (Mail::send()).
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendMailNotificationJob that carries the pre-built Mailable, avoiding the
 * need to re-invoke toMail() inside the Worker.
 *
 * Requires: a MailerInterface (NotificationServiceProvider passes the bound
 * one), or MailServiceProvider for the façade fallback. The queued
 * SendMailNotificationJob always uses the façade — a serialized job cannot
 * carry the mailer.
 *
 * @package EzPhp\Notification\Channel
 */
final class MailChannel implements QueuableChannelInterface
{
    /**
     * @param MailerInterface|null $mailer Delivers the mail; null = the Mail façade.
     */
    public function __construct(private readonly ?MailerInterface $mailer = null)
    {
    }

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

        $mailable = $notification->toMail($notifiable);

        if ($this->mailer !== null) {
            $this->mailer->send($mailable);

            return;
        }

        Mail::send($mailable);
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
