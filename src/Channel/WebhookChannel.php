<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationException;
use EzPhp\Notification\NotificationInterface;
use EzPhp\Webhook\WebhookDispatcher;

/**
 * Class WebhookChannel
 *
 * Delivers ToWebhookInterface notifications through ez-php/webhook's
 * WebhookDispatcher, which signs the JSON payload and queues a retrying
 * DeliverWebhookJob. Not a QueuableChannelInterface: the dispatcher already
 * queues, so a second queue hop would add nothing.
 *
 * ez-php/webhook is a soft dependency (`suggest`); NotificationServiceProvider
 * registers this channel only when a WebhookDispatcher is bound.
 *
 * @package EzPhp\Notification\Channel
 */
final readonly class WebhookChannel implements ChannelInterface
{
    /**
     * @param WebhookDispatcher $dispatcher
     */
    public function __construct(private WebhookDispatcher $dispatcher)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if (!$notification instanceof ToWebhookInterface) {
            throw new NotificationException(sprintf(
                'Notification %s must implement %s to use the webhook channel.',
                $notification::class,
                ToWebhookInterface::class,
            ));
        }

        $this->dispatcher->dispatch(
            $notification->webhookUrl($notifiable),
            $notification->toWebhook($notifiable),
            $notification->webhookSecret($notifiable),
        );
    }
}
