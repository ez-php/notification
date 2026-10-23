<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Broadcast\Broadcaster;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Notification\QueuableChannelInterface;
use EzPhp\Notification\Queue\SendBroadcastNotificationJob;
use EzPhp\Queue\Job;

/**
 * Class BroadcastChannel
 *
 * Delivers notifications via ez-php/broadcast: the injected Broadcaster's to()
 * (or the Broadcast façade when none is injected) receives the channel name,
 * event name, and payload returned by the notification's broadcast methods.
 *
 * Implements QueuableChannelInterface — when the Notifier detects a
 * ShouldQueueInterface notification, it calls toJob() to get a
 * SendBroadcastNotificationJob that carries the pre-resolved channel/event/
 * payload, avoiding re-invocation inside the Worker.
 *
 * Requires: BroadcastServiceProvider must be registered in the application
 * (it binds Broadcaster and wires the façade).
 *
 * @package EzPhp\Notification\Channel
 */
final class BroadcastChannel implements QueuableChannelInterface
{
    /**
     * @param Broadcaster|null $broadcaster Publishes the event; null = the Broadcast façade.
     */
    public function __construct(private readonly ?Broadcaster $broadcaster = null)
    {
    }

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

        $channel = $notification->broadcastOn($notifiable);
        $event = $notification->broadcastAs($notifiable);
        $payload = $notification->broadcastWith($notifiable);

        if ($this->broadcaster !== null) {
            $this->broadcaster->to($channel, $event, $payload);

            return;
        }

        Broadcast::to($channel, $event, $payload);
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
