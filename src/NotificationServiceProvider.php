<?php

declare(strict_types=1);

namespace EzPhp\Notification;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Mail\MailerInterface;
use EzPhp\Notification\Channel\BroadcastChannel;
use EzPhp\Notification\Channel\DatabaseChannel;
use EzPhp\Notification\Channel\MailChannel;
use EzPhp\Notification\Channel\PushChannel;
use EzPhp\Notification\Channel\SmsChannel;
use EzPhp\Notification\Channel\WebhookChannel;
use EzPhp\Push\Pusher;
use EzPhp\Sms\SmsSenderInterface;
use EzPhp\Webhook\WebhookDispatcher;

/**
 * Class NotificationServiceProvider
 *
 * Registers the Notifier in the DI container and wires the Notification facade.
 *
 * Built-in channels registered:
 *   - 'mail'      → MailChannel      (requires MailServiceProvider)
 *   - 'broadcast' → BroadcastChannel (requires BroadcastServiceProvider)
 *   - 'push'      → PushChannel      (requires PushServiceProvider)
 *   - 'database'  → DatabaseChannel  (requires DatabaseServiceProvider; optional)
 *   - 'sms'       → SmsChannel       (requires ez-php/sms's SmsServiceProvider; optional)
 *   - 'webhook'   → WebhookChannel   (requires ez-php/webhook's WebhookServiceProvider; optional)
 *
 * Optional integrations (resolved gracefully when unavailable):
 *   - DatabaseInterface — if not bound, the 'database' channel is omitted
 *   - QueueInterface    — if not bound, all notifications are sent synchronously
 *
 * @package EzPhp\Notification
 */
final class NotificationServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(Notifier::class, function (ContainerInterface $app): Notifier {
            $channels = [
                // Bound services are injected; without a binding the channels fall back
                // to their module's façade (Mail::send(), Push::send()).
                'mail' => new MailChannel($app->has(MailerInterface::class) ? $app->make(MailerInterface::class) : null),
                'broadcast' => new BroadcastChannel(),
                'push' => new PushChannel($app->has(Pusher::class) ? $app->make(Pusher::class) : null),
            ];

            // The database channel requires DatabaseInterface. Register it only
            // when a database binding is available so that applications without
            // a database can still use the mail/broadcast channels.
            if ($app->has(DatabaseInterface::class)) {
                $channels['database'] = new DatabaseChannel(
                    $app->make(DatabaseInterface::class)->getPdo()
                );
            }

            // The webhook channel needs ez-php/webhook (a suggest) and its dispatcher binding.
            if (class_exists(WebhookDispatcher::class) && $app->has(WebhookDispatcher::class)) {
                $channels['webhook'] = new WebhookChannel($app->make(WebhookDispatcher::class));
            }

            // The sms channel needs ez-php/sms (a suggest) and its sender binding.
            if (interface_exists(SmsSenderInterface::class) && $app->has(SmsSenderInterface::class)) {
                $channels['sms'] = new SmsChannel($app->make(SmsSenderInterface::class));
            }

            // Queue support is optional. When QueueInterface is not bound,
            // ShouldQueueInterface notifications are sent synchronously.
            $queue = $app->has(QueueInterface::class)
                ? $app->make(QueueInterface::class)
                : null;

            return new Notifier($channels, $queue);
        });
    }

    /**
     * @return void
     */
    public function boot(): void
    {
        Notification::setNotifier($this->app->make(Notifier::class));
    }
}
