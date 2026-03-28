<?php

declare(strict_types=1);

namespace EzPhp\Notification;

/**
 * Interface ChannelInterface
 *
 * Contract for notification delivery channels.
 *
 * Each channel is responsible for delivering a notification to one transport
 * (mail, broadcast, database, etc.). Channels are instantiated and registered
 * by NotificationServiceProvider.
 *
 * @package EzPhp\Notification
 */
interface ChannelInterface
{
    /**
     * Deliver the notification to the notifiable via this channel.
     *
     * @param NotifiableInterface  $notifiable  The recipient object.
     * @param NotificationInterface $notification The notification to send.
     *
     * @throws NotificationException When the notification does not implement
     *                               the required channel data interface.
     *
     * @return void
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void;
}
