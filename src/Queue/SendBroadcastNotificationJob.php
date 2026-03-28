<?php

declare(strict_types=1);

namespace EzPhp\Notification\Queue;

use EzPhp\Broadcast\Broadcast;
use EzPhp\Queue\Job;

/**
 * Class SendBroadcastNotificationJob
 *
 * Queue Job that publishes a pre-resolved broadcast event via Broadcast::to().
 *
 * Created by BroadcastChannel::toJob() when a ShouldQueueInterface
 * notification uses the 'broadcast' channel. The channel name, event name,
 * and payload are resolved from the notification before the Job is serialised,
 * so broadcastOn/broadcastAs/broadcastWith are not called again inside the
 * Worker.
 *
 * @package EzPhp\Notification\Queue
 */
final class SendBroadcastNotificationJob extends Job
{
    /**
     * SendBroadcastNotificationJob Constructor
     *
     * @param string               $channel The broadcast channel name.
     * @param string               $event   The broadcast event name.
     * @param array<string, mixed> $payload The event payload.
     */
    public function __construct(
        private readonly string $channel,
        private readonly string $event,
        private readonly array $payload,
    ) {
    }

    /**
     * Publish the notification via Broadcast::to().
     *
     * @return void
     */
    public function handle(): void
    {
        Broadcast::to($this->channel, $this->event, $this->payload);
    }
}
