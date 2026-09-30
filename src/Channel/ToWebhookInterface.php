<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use EzPhp\Notification\NotifiableInterface;

/**
 * Interface ToWebhookInterface
 *
 * Implemented by notifications that are delivered to an HTTP endpoint — a
 * customer's webhook, a Slack/Teams incoming-webhook URL, … — through the
 * 'webhook' channel (ez-php/webhook: HMAC-signed, queued, retried).
 *
 * Slack example:
 *   public function webhookUrl(NotifiableInterface $n): string { return $n->slackWebhookUrl(); }
 *   public function webhookSecret(NotifiableInterface $n): string { return ''; } // Slack does not verify
 *   public function toWebhook(NotifiableInterface $n): array { return ['text' => 'Order shipped']; }
 *
 * @package EzPhp\Notification\Channel
 */
interface ToWebhookInterface
{
    /**
     * @param NotifiableInterface $notifiable
     *
     * @return string Destination URL.
     */
    public function webhookUrl(NotifiableInterface $notifiable): string;

    /**
     * @param NotifiableInterface $notifiable
     *
     * @return string Signing secret shared with the receiver ('' when it does not verify signatures).
     */
    public function webhookSecret(NotifiableInterface $notifiable): string;

    /**
     * @param NotifiableInterface $notifiable
     *
     * @return array<string, mixed> JSON payload.
     */
    public function toWebhook(NotifiableInterface $notifiable): array;
}
