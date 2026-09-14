<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendPushNotificationJob;
use EzPhp\Push\Push;
use EzPhp\Queue\Job;

/**
 * Class PushChannel
 *
 * Delivers notifications via ez-php/push by calling Push::send() with the
 * device token and PushMessage returned by the notification's pushToken()
 * and toPush() methods.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendPushNotificationJob that carries the pre-resolved token/message,
 * avoiding re-invocation inside the Worker.
 *
 * Requires: PushServiceProvider must be registered in the application.
 *
 * @package EzPhp\Notification\Channel
 */
final class PushChannel implements QueuableChannelInterface
{
    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!$notification instanceof ToPushInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the push channel.',
                $notification::class,
                ToPushInterface::class,
            ));
        }

        Push::send($notification->pushToken($notifiable), $notification->toPush($notifiable));
    }

    /**
     * {@inheritDoc}
     */
    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job
    {
        if (!$notification instanceof ToPushInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the push channel.',
                $notification::class,
                ToPushInterface::class,
            ));
        }

        return new SendPushNotificationJob(
            $notification->pushToken($notifiable),
            $notification->toPush($notifiable),
        );
    }
}
