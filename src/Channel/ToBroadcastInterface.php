<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;

/**
 * Interface ToBroadcastInterface
 *
 * Implemented by notifications that support the 'broadcast' channel.
 *
 * Usage:
 *   public function via(): array { return ['broadcast']; }
 *   public function broadcastOn(NotifiableInterface $notifiable): string
 *   {
 *       return 'users.' . $notifiable->routeNotificationFor('broadcast');
 *   }
 *   public function broadcastAs(NotifiableInterface $notifiable): string
 *   {
 *       return 'notification.created';
 *   }
 *   public function broadcastWith(NotifiableInterface $notifiable): array
 *   {
 *       return ['message' => 'Hello!'];
 *   }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToBroadcastInterface
{
    /**
     * Return the broadcast channel name.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return string
     */
    public function broadcastOn(NotifiableInterface $notifiable): string;

    /**
     * Return the broadcast event name.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return string
     */
    public function broadcastAs(NotifiableInterface $notifiable): string;

    /**
     * Return the broadcast payload.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(NotifiableInterface $notifiable): array;
}
