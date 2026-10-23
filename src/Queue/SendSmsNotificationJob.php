<?php

declare(strict_types=1);

namespace EzPhp\Notification\Queue;

use EzPhp\Queue\Job;
use EzPhp\Sms\Sms;
use EzPhp\Sms\SmsMessage;

/**
 * Class SendSmsNotificationJob
 *
 * Queue Job that delivers a pre-resolved SMS via Sms::send().
 *
 * Created by SmsChannel::toJob() when a ShouldQueueInterface notification
 * uses the 'sms' channel. The phone number and SmsMessage are resolved from
 * the notification before the Job is serialised, so smsTo()/toSms() are not
 * called again inside the Worker.
 *
 * @package EzPhp\Notification\Queue
 */
final class SendSmsNotificationJob extends Job
{
    /**
     * SendSmsNotificationJob Constructor
     *
     * @param string     $to      The phone number to deliver to.
     * @param SmsMessage $message The SMS content.
     */
    public function __construct(
        private readonly string $to,
        private readonly SmsMessage $message,
    ) {
    }

    /**
     * Deliver the SMS via Sms::send().
     *
     * @return void
     */
    public function handle(): void
    {
        Sms::send($this->to, $this->message);
    }
}
