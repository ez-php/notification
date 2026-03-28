<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Mail\Mailable;
use EzPhp\Notification\NotifiableInterface;

/**
 * Interface ToMailInterface
 *
 * Implemented by notifications that support the 'mail' channel.
 *
 * Usage:
 *   public function via(): array { return ['mail']; }
 *   public function toMail(NotifiableInterface $notifiable): Mailable
 *   {
 *       return (new Mailable())
 *           ->to((string) $notifiable->routeNotificationFor('mail'))
 *           ->subject('Hello')
 *           ->text('Welcome!');
 *   }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToMailInterface
{
    /**
     * Build the Mailable for this notification.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return Mailable
     */
    public function toMail(NotifiableInterface $notifiable): Mailable;
}
