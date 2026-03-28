# ez-php/notification

Multi-channel notification orchestration for ez-php applications. Routes a single notification to any combination of mail, broadcast, and database channels. Optionally dispatches deliveries asynchronously via the queue.

## Installation

```bash
composer require ez-php/notification
```

Register the provider in `provider/modules.php`:

```php
EzPhp\Notification\NotificationServiceProvider::class,
```

## Quick Start

### 1 — Define a notifiable (e.g. your User model)

```php
use EzPhp\Notification\NotifiableInterface;

class User implements NotifiableInterface
{
    public function __construct(
        public readonly int    $id,
        public readonly string $email,
    ) {}

    public function routeNotificationFor(string $channel): string|int
    {
        return match ($channel) {
            'mail'      => $this->email,
            'broadcast' => 'users.' . $this->id,
            'database'  => $this->id,
        };
    }
}
```

### 2 — Define a notification

```php
use EzPhp\Mail\Mailable;
use EzPhp\Notification\Channel\ToMailInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;

class WelcomeNotification implements NotificationInterface, ToMailInterface
{
    public function via(): array
    {
        return ['mail'];
    }

    public function toMail(NotifiableInterface $notifiable): Mailable
    {
        return (new Mailable())
            ->to((string) $notifiable->routeNotificationFor('mail'))
            ->subject('Welcome!')
            ->text('Thanks for signing up.');
    }
}
```

### 3 — Send it

```php
use EzPhp\Notification\Notification;

Notification::send($user, new WelcomeNotification());
```

## Channels

### mail

Delivers via `ez-php/mail`. The notification must implement `ToMailInterface`:

```php
public function toMail(NotifiableInterface $notifiable): Mailable;
```

### broadcast

Delivers via `ez-php/broadcast`. The notification must implement `ToBroadcastInterface`:

```php
public function broadcastOn(NotifiableInterface $notifiable): string;   // channel name
public function broadcastAs(NotifiableInterface $notifiable): string;   // event name
public function broadcastWith(NotifiableInterface $notifiable): array;  // payload
```

### database

Persists to the `notifications` table. The notification must implement `ToDatabaseInterface`:

```php
public function toDatabase(NotifiableInterface $notifiable): array;  // JSON payload
```

The table is auto-created on first use. To create it via migration instead:

```sql
-- MySQL
CREATE TABLE notifications (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type            VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id   VARCHAR(255) NOT NULL,
    data            JSON         NOT NULL,
    read_at         DATETIME     NULL,
    created_at      DATETIME     NOT NULL
);

-- SQLite
CREATE TABLE notifications (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    type            TEXT NOT NULL,
    notifiable_type TEXT NOT NULL,
    notifiable_id   TEXT NOT NULL,
    data            TEXT NOT NULL,
    read_at         TEXT NULL,
    created_at      TEXT NOT NULL
);
```

## Multi-channel

Return multiple channels from `via()` and implement the matching interfaces:

```php
class OrderShippedNotification implements
    NotificationInterface,
    ToMailInterface,
    ToBroadcastInterface,
    ToDatabaseInterface
{
    public function via(): array
    {
        return ['mail', 'broadcast', 'database'];
    }

    // toMail(), broadcastOn(), broadcastAs(), broadcastWith(), toDatabase() ...
}
```

## Async delivery (queue)

Add `ShouldQueueInterface` to defer mail and broadcast channels via the queue:

```php
use EzPhp\Notification\ShouldQueueInterface;

class WelcomeNotification implements NotificationInterface, ShouldQueueInterface, ToMailInterface
{
    // ...
}
```

When `QueueInterface` is bound (i.e. `QueueServiceProvider` is registered), the Notifier
pushes `SendMailNotificationJob` / `SendBroadcastNotificationJob` onto the queue instead of
delivering synchronously. The `database` channel always runs synchronously.

To force synchronous delivery regardless of `ShouldQueueInterface`:

```php
Notification::sendNow($user, new WelcomeNotification());
```

## Service Provider wiring

`NotificationServiceProvider` registers the following channels automatically:

| Channel | Requires | Optional |
|---------|----------|----------|
| `mail` | `MailServiceProvider` | — |
| `broadcast` | `BroadcastServiceProvider` | — |
| `database` | `DatabaseServiceProvider` | yes — omitted if not bound |

Queue support (`ShouldQueueInterface`) is also optional — if `QueueServiceProvider` is not
registered, all notifications are sent synchronously.

## Testing

Inject a real `Notifier` with spy channels in your tests:

```php
use EzPhp\Notification\Notification;
use EzPhp\Notification\Notifier;

protected function setUp(): void
{
    $this->spyChannel = new SpyChannel();
    Notification::setNotifier(new Notifier(['mail' => $this->spyChannel]));
}

protected function tearDown(): void
{
    Notification::resetNotifier();
}
```
