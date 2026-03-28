<?php

declare(strict_types=1);

namespace EzPhp\Notification;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Notification\Channel\BroadcastChannel;
use EzPhp\Notification\Channel\DatabaseChannel;
use EzPhp\Notification\Channel\MailChannel;

/**
 * Class NotificationServiceProvider
 *
 * Registers the Notifier in the DI container and wires the Notification facade.
 *
 * Built-in channels registered:
 *   - 'mail'      → MailChannel      (requires MailServiceProvider)
 *   - 'broadcast' → BroadcastChannel (requires BroadcastServiceProvider)
 *   - 'database'  → DatabaseChannel  (requires DatabaseServiceProvider; optional)
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
                'mail' => new MailChannel(),
                'broadcast' => new BroadcastChannel(),
            ];

            // The database channel requires DatabaseInterface. Register it only
            // when a database binding is available so that applications without
            // a database can still use the mail/broadcast channels.
            try {
                $channels['database'] = new DatabaseChannel(
                    $app->make(DatabaseInterface::class)->getPdo()
                );
            } catch (\Throwable) {
                // DatabaseInterface not registered — 'database' channel unavailable.
            }

            // Queue support is optional. When QueueInterface is not bound,
            // ShouldQueueInterface notifications are sent synchronously.
            $queue = null;
            try {
                $queue = $app->make(QueueInterface::class);
            } catch (\Throwable) {
                // QueueInterface not registered — all notifications sent synchronously.
            }

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
