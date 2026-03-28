# Changelog

All notable changes to `ez-php/notification` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.1.0] — 2026-03-28

### Added
- `NotificationInterface` — contract for all notifications; `via()` returns the list of delivery channels
- `NotifiableInterface` — contract for notifiable entities; `routeNotificationFor(channel)` returns the channel-specific address
- `Notifier` — core orchestrator; routes a notification to all channels returned by `via()`, with optional queue-backed async delivery
- `Notification` — static façade wrapping `Notifier`; `send()` and `sendNow()`
- `NotificationServiceProvider` — registers `Notifier` and all built-in channels; auto-detects Mail, Broadcast, Database, and Queue service providers
- `MailChannel` — delivers via `ez-php/mail`; notification must implement `ToMailInterface`
- `BroadcastChannel` — delivers via `ez-php/broadcast`; notification must implement `ToBroadcastInterface`
- `DatabaseChannel` — persists to the `notifications` table; notification must implement `ToDatabaseInterface`; table is auto-created on first use
- `ShouldQueueInterface` — marker interface; when bound, `MailChannel` and `BroadcastChannel` push jobs onto the queue instead of delivering synchronously
- `SendMailNotificationJob` and `SendBroadcastNotificationJob` — queueable delivery jobs
- Queue and database support are fully optional — the module works without `ez-php/queue` or `ez-php/orm`
