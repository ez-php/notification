<?php

declare(strict_types=1);

namespace EzPhp\Notification;

use EzPhp\Queue\Job;

/**
 * Interface QueuableChannelInterface
 *
 * Extends ChannelInterface with the ability to produce a self-contained queue
 * Job for deferred delivery.
 *
 * When the Notifier encounters a ShouldQueueInterface notification, it calls
 * toJob() on channels that implement this interface and pushes the returned
 * Job onto the queue instead of sending synchronously. Channels that do NOT
 * implement this interface are always delivered synchronously, regardless of
 * ShouldQueueInterface.
 *
 * @package EzPhp\Notification
 */
interface QueuableChannelInterface extends ChannelInterface
{
    /**
     * Build and return a queue Job that delivers this notification via this channel.
     *
     * The returned Job must be self-contained — all data needed for delivery
     * (e.g. resolved Mailable, broadcast payload) must be embedded in the Job
     * at construction time, because the Job will be PHP-serialised and executed
     * later by a Worker.
     *
     * @param NotifiableInterface   $notifiable   The recipient object.
     * @param NotificationInterface $notification The notification to send.
     *
     * @throws NotificationException When the notification does not implement
     *                               the required channel data interface.
     *
     * @return Job
     */
    public function toJob(NotifiableInterface $notifiable, NotificationInterface $notification): Job;
}
