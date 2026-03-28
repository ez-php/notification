<?php

declare(strict_types=1);

namespace EzPhp\Notification;

/**
 * Interface NotificationInterface
 *
 * Contract for all notification classes. Implement this interface and return
 * the list of channels (e.g. 'mail', 'broadcast', 'database') the notification
 * should be delivered through.
 *
 * For each channel listed in via(), the notification class must also implement
 * the corresponding channel data interface:
 *   - 'mail'      → ToMailInterface
 *   - 'broadcast' → ToBroadcastInterface
 *   - 'database'  → ToDatabaseInterface
 *
 * @package EzPhp\Notification
 */
interface NotificationInterface
{
    /**
     * Return the list of channels this notification should be sent through.
     *
     * @return list<string>
     */
    public function via(): array;
}
