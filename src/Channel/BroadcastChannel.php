<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendBroadcastNotificationJob;
use EzPhp\Queue\Job;

/**
 * Class BroadcastChannel
 *
 * Delivers notifications via ez-php/broadcast by calling Broadcast::to() with
 * the channel name, event name, and payload returned by the notification's
 * broadcast methods.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendBroadcastNotificationJob that carries the pre-resolved channel/event/
 * payload, avoiding re-invocation inside the Worker.
 *
 * Requires: BroadcastServiceProvider must be registered in the application.
 *
 * @package EzPhp\Notification\Channel
 */
final class BroadcastChannel implements QueuableChannelInterface
{
    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!$notification instanceof ToBroadcastInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the broadcast channel.',
                $notification::class,
                ToBroadcastInterface::class,
            ));
        }

        Broadcast::to(
            $notification->broadcastOn($notifiable),
            $notification->broadcastAs($notifiable),
            $notification->broadcastWith($notifiable),
        );
    }

    /**
     * {@inheritDoc}
     */
    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job
    {
        if (!$notification instanceof ToBroadcastInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the broadcast channel.',
                $notification::class,
                ToBroadcastInterface::class,
            ));
        }

        return new SendBroadcastNotificationJob(
            $notification->broadcastOn($notifiable),
            $notification->broadcastAs($notifiable),
            $notification->broadcastWith($notifiable),
        );
    }
}
