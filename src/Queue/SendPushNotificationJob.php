<?php

declare(strict_types=1);

namespace EzPhp\Notification\Queue;

use EzPhp\Push\Push;
use EzPhp\Push\PushMessage;
use EzPhp\Queue\Job;

/**
 * Class SendPushNotificationJob
 *
 * Queue Job that delivers a pre-resolved push notification via Push::send().
 *
 * Created by PushChannel::toJob() when a ShouldQueueInterface notification
 * uses the 'push' channel. The device token and PushMessage are resolved
 * from the notification before the Job is serialised, so pushToken()/toPush()
 * are not called again inside the Worker.
 *
 * @package EzPhp\Notification\Queue
 */
final class SendPushNotificationJob extends Job
{
    /**
     * SendPushNotificationJob Constructor
     *
     * @param string      $token   The device token to deliver to.
     * @param PushMessage $message The push notification content.
     */
    public function __construct(
        private readonly string $token,
        private readonly PushMessage $message,
    ) {
    }

    /**
     * Deliver the notification via Push::send().
     *
     * @return void
     */
    public function handle(): void
    {
        Push::send($this->token, $this->message);
    }
}
