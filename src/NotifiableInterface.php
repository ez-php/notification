<?php

declare(strict_types=1);

namespace EzPhp\Notification;

/**
 * Interface NotifiableInterface
 *
 * Contract for objects that can receive notifications (e.g. User, Admin).
 *
 * Implement routeNotificationFor() to tell each channel where to deliver the
 * notification:
 *   - 'mail'      → email address string
 *   - 'broadcast' → channel name string
 *   - 'database'  → entity ID (int or string)
 *
 * @package EzPhp\Notification
 */
interface NotifiableInterface
{
    /**
     * Return the routing address for the given channel.
     *
     * @param string $channel Channel name: 'mail', 'broadcast', or 'database'.
     *
     * @return string|int
     */
    public function routeNotificationFor(string $channel): string|int;
}
