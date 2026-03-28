<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;

/**
 * Interface ToDatabaseInterface
 *
 * Implemented by notifications that support the 'database' channel.
 *
 * The returned array is stored as a JSON payload in the `notifications` table.
 * The DatabaseChannel auto-creates the table on first use.
 *
 * Usage:
 *   public function via(): array { return ['database']; }
 *   public function toDatabase(NotifiableInterface $notifiable): array
 *   {
 *       return ['message' => 'You have a new message.', 'url' => '/inbox'];
 *   }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToDatabaseInterface
{
    /**
     * Build the data payload to store in the notifications table.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return array<string, mixed>
     */
    public function toDatabase(NotifiableInterface $notifiable): array;
}
