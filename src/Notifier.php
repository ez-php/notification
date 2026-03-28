<?php

declare(strict_types=1);

namespace EzPhp\Notification;

use EzPhp\Contracts\QueueInterface;

/**
 * Class Notifier
 *
 * Core dispatcher that routes notifications to the appropriate channels.
 *
 * Channels are keyed by name (e.g. 'mail', 'broadcast', 'database') and
 * registered at construction time by NotificationServiceProvider.
 *
 * Synchronous vs. queued delivery:
 * - If the notification implements ShouldQueueInterface AND a QueueInterface
 *   is available, each channel that implements QueuableChannelInterface has
 *   its toJob() called and the resulting Job is pushed onto the queue.
 *   Channels that do NOT implement QueuableChannelInterface (e.g. database)
 *   are still executed synchronously.
 * - If no QueueInterface is available, or the notification does not implement
 *   ShouldQueueInterface, sendNow() is called directly.
 *
 * @package EzPhp\Notification
 */
final class Notifier
{
    /**
     * Notifier Constructor
     *
     * @param array<string, ChannelInterface> $channels  Map of channel name → ChannelInterface.
     * @param QueueInterface|null             $queue     Queue driver, or null when not available.
     */
    public function __construct(
        private readonly array $channels,
        private readonly ?QueueInterface $queue = null,
    ) {
    }

    /**
     * Send the notification — queued if applicable, synchronous otherwise.
     *
     * @param NotifiableInterface  $notifiable
     * @param NotificationInterface $notification
     *
     * @return void
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        if ($notification instanceof ShouldQueueInterface && $this->queue !== null) {
            foreach ($notification->via() as $channelName) {
                $channel = $this->resolveChannel($channelName);

                if ($channel instanceof QueuableChannelInterface) {
                    $this->queue->push($channel->toJob($notifiable, $notification));
                } else {
                    $channel->send($notifiable, $notification);
                }
            }

            return;
        }

        $this->sendNow($notifiable, $notification);
    }

    /**
     * Send the notification synchronously, bypassing ShouldQueueInterface.
     *
     * @param NotifiableInterface   $notifiable
     * @param NotificationInterface $notification
     *
     * @return void
     */
    public function sendNow(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        foreach ($notification->via() as $channelName) {
            $this->resolveChannel($channelName)->send($notifiable, $notification);
        }
    }

    /**
     * Resolve a channel by name.
     *
     * @param string $name
     *
     * @throws NotificationException When no channel with this name is registered.
     *
     * @return ChannelInterface
     */
    private function resolveChannel(string $name): ChannelInterface
    {
        if (!isset($this->channels[$name])) {
            throw new NotificationException(
                "Unknown notification channel: '{$name}'. " .
                'Registered channels: ' . implode(', ', array_keys($this->channels)) . '.'
            );
        }

        return $this->channels[$name];
    }
}
