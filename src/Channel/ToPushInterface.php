<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Push\PushMessage;

/**
 * Interface ToPushInterface
 *
 * Implemented by notifications that support the 'push' channel.
 *
 * Usage:
 *   public function via(): array { return ['push']; }
 *   public function pushToken(NotifiableInterface $notifiable): string
 *   {
 *       return (string) $notifiable->routeNotificationFor('push');
 *   }
 *   public function toPush(NotifiableInterface $notifiable): PushMessage
 *   {
 *       return new PushMessage('Hello', 'Welcome!');
 *   }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToPushInterface
{
    /**
     * Return the device token to deliver this notification to.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return string
     */
    public function pushToken(NotifiableInterface $notifiable): string;

    /**
     * Build the PushMessage for this notification.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return PushMessage
     */
    public function toPush(NotifiableInterface $notifiable): PushMessage;
}
