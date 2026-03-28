<?php

declare(strict_types=1);

namespace EzPhp\Notification;

/**
 * Interface ShouldQueueInterface
 *
 * Marker interface for notifications that should be delivered asynchronously
 * via the queue.
 *
 * When a notification implements ShouldQueueInterface and a QueueInterface
 * binding is registered in the container, the Notifier will call toJob() on
 * each QueuableChannelInterface channel and push the resulting Jobs onto the
 * queue. Channels that do not implement QueuableChannelInterface are still
 * executed synchronously.
 *
 * Usage:
 *   class WelcomeNotification implements NotificationInterface, ShouldQueueInterface, ToMailInterface
 *   {
 *       public function via(): array { return ['mail']; }
 *       public function toMail(NotifiableInterface $notifiable): Mailable { ... }
 *   }
 *
 * @package EzPhp\Notification
 */
interface ShouldQueueInterface
{
}
