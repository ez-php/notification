<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;
use EzPhp\Sms\SmsMessage;

/**
 * Interface ToSmsInterface
 *
 * Implemented by notifications that support the 'sms' channel.
 *
 * Usage:
 *   public function via(): array { return ['sms']; }
 *   public function smsTo(NotifiableInterface $notifiable): string
 *   {
 *       return (string) $notifiable->routeNotificationFor('sms');
 *   }
 *   public function toSms(NotifiableInterface $notifiable): SmsMessage
 *   {
 *       return new SmsMessage('Your order has shipped.');
 *   }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToSmsInterface
{
    /**
     * Return the phone number (E.164) to deliver this notification to.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return string
     */
    public function smsTo(NotifiableInterface $notifiable): string;

    /**
     * Build the SmsMessage for this notification.
     *
     * @param NotifiableInterface $notifiable
     *
     * @return SmsMessage
     */
    public function toSms(NotifiableInterface $notifiable): SmsMessage;
}
