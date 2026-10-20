<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendPushNotificationJob;
use EzPhp\Push\Push;
use EzPhp\Push\Pusher;
use EzPhp\Queue\Job;

/**
 * Class PushChannel
 *
 * Delivers notifications via ez-php/push: the device token and PushMessage
 * returned by the notification's pushToken() and toPush() methods go to the
 * injected Pusher, or — constructed without one — to the Push façade.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendPushNotificationJob that carries the pre-resolved token/message,
 * avoiding re-invocation inside the Worker.
 *
 * Requires: a Pusher (NotificationServiceProvider passes the bound one), or
 * PushServiceProvider for the façade fallback. The queued
 * SendPushNotificationJob always uses the façade.
 *
 * @package EzPhp\Notification\Channel
 */
final class PushChannel implements QueuableChannelInterface
{
    /**
     * @param Pusher|null $pusher Delivers the push; null = the Push façade.
     */
    public function __construct(private readonly ?Pusher $pusher = null)
    {
    }

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

        $token = $notification->pushToken($notifiable);
        $message = $notification->toPush($notifiable);

        if ($this->pusher !== null) {
            $this->pusher->send($token, $message);

            return;
        }

        Push::send($token, $message);
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
