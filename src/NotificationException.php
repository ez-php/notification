<?php

declare(strict_types=1);

namespace EzPhp\Notification;

use EzPhp\Contracts\EzPhpException;

/**
 * Class NotificationException
 *
 * Base exception for all notification-related errors.
 *
 * Thrown when:
 * - A notification does not implement the required channel data interface
 *   (e.g. ToMailInterface for the 'mail' channel).
 * - A requested channel name is not registered in the Notifier.
 *
 * @package EzPhp\Notification
 */
final class NotificationException extends EzPhpException
{
}
