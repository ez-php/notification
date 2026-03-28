<?php

declare(strict_types=1);

namespace EzPhp\Notification\Queue;

use EzPhp\Mail\Mail;
use EzPhp\Mail\Mailable;
use EzPhp\Queue\Job;

/**
 * Class SendMailNotificationJob
 *
 * Queue Job that delivers a pre-built Mailable via Mail::send().
 *
 * Created by MailChannel::toJob() when a ShouldQueueInterface notification
 * uses the 'mail' channel. The Mailable is resolved from the notification
 * before the Job is serialised, so toMail() is not called again inside the
 * Worker.
 *
 * @package EzPhp\Notification\Queue
 */
final class SendMailNotificationJob extends Job
{
    /**
     * SendMailNotificationJob Constructor
     *
     * @param Mailable $mailable The pre-built mail message to deliver.
     */
    public function __construct(private readonly Mailable $mailable)
    {
    }

    /**
     * Send the notification via Mail::send().
     *
     * @return void
     */
    public function handle(): void
    {
        Mail::send($this->mailable);
    }
}
