<?php

declare(strict_types=1);

namespace Tests\Queue;

use EzPhp\Mail\Mailable;
use EzPhp\Notification\Queue\SendBroadcastNotificationJob;
use EzPhp\Notification\Queue\SendMailNotificationJob;
use EzPhp\Notification\Queue\SendPushNotificationJob;
use EzPhp\Push\PushMessage;
use EzPhp\Queue\Driver\InMemoryDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * The queued notification jobs hold objects (Mailable, PushMessage); they must
 * survive a queue push/pop — pop() used to fail with a TypeError on them.
 *
 * @package Tests\Queue
 */
#[CoversClass(SendMailNotificationJob::class)]
#[CoversClass(SendPushNotificationJob::class)]
#[CoversClass(SendBroadcastNotificationJob::class)]
final class NotificationQueueJobRoundTripTest extends TestCase
{
    public function testMailNotificationJobSurvivesQueueRoundTrip(): void
    {
        $queue = new InMemoryDriver();
        $queue->push(new SendMailNotificationJob((new Mailable())->to('user@example.com')->subject('Hi')->text('Hello')));

        $this->assertInstanceOf(SendMailNotificationJob::class, $queue->pop());
    }

    public function testPushNotificationJobSurvivesQueueRoundTrip(): void
    {
        $queue = new InMemoryDriver();
        $queue->push(new SendPushNotificationJob('device-token', new PushMessage('Title', 'Body', ['k' => 'v'])));

        $this->assertInstanceOf(SendPushNotificationJob::class, $queue->pop());
    }

    public function testBroadcastNotificationJobSurvivesQueueRoundTrip(): void
    {
        $queue = new InMemoryDriver();
        $queue->push(new SendBroadcastNotificationJob('orders', 'shipped', ['id' => 1]));

        $this->assertInstanceOf(SendBroadcastNotificationJob::class, $queue->pop());
    }
}
