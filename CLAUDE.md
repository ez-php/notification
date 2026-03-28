# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/notification

Multi-channel notification orchestration for ez-php — a `Notification` static facade backed by a `Notifier` that routes to built-in Mail, Broadcast, and Database channels with optional queue-backed async delivery.

---

## Source Structure

```
src/
├── NotificationInterface.php          — via(): list<string>; contract all notification classes implement
├── NotifiableInterface.php            — routeNotificationFor(channel): string|int; implemented by User etc.
├── ChannelInterface.php               — send(notifiable, notification): void; contract for all channels
├── QueuableChannelInterface.php       — extends ChannelInterface + toJob(): Job; channels that support async delivery
├── ShouldQueueInterface.php           — marker: Notifier queues QueuableChannels instead of calling send()
├── NotificationException.php          — base exception: unknown channel, missing channel data interface
├── Notifier.php                       — core dispatcher: resolves channels, handles sync/queued delivery
├── Notification.php                   — static facade backed by Notifier singleton; set/reset/send/sendNow
├── NotificationServiceProvider.php    — registers Notifier with channels + queue; wires facade in boot()
├── Channel/
│   ├── ToMailInterface.php            — toMail(notifiable): Mailable; required for 'mail' channel
│   ├── ToBroadcastInterface.php       — broadcastOn/broadcastAs/broadcastWith; required for 'broadcast' channel
│   ├── ToDatabaseInterface.php        — toDatabase(notifiable): array; required for 'database' channel
│   ├── MailChannel.php                — implements QueuableChannelInterface; calls Mail::send()
│   ├── BroadcastChannel.php           — implements QueuableChannelInterface; calls Broadcast::to()
│   └── DatabaseChannel.php            — implements ChannelInterface; inserts into notifications table (auto-created)
└── Queue/
    ├── SendMailNotificationJob.php    — Job storing pre-built Mailable; handle() calls Mail::send()
    └── SendBroadcastNotificationJob.php — Job storing channel/event/payload; handle() calls Broadcast::to()

tests/
├── TestCase.php                       — base PHPUnit test case
├── NotifierTest.php                   — covers Notifier: sync dispatch, multi-channel, queuing, sendNow bypass
├── NotificationTest.php               — covers Notification facade: delegation, uninitialized throw, reset, replace
└── Channel/
    ├── MailChannelTest.php            — covers MailChannel + SendMailNotificationJob
    ├── BroadcastChannelTest.php       — covers BroadcastChannel + SendBroadcastNotificationJob
    └── DatabaseChannelTest.php        — covers DatabaseChannel with SQLite :memory: (no MySQL required)
```

---

## Key Classes and Responsibilities

### NotificationInterface (`src/NotificationInterface.php`)

Single-method contract:

```php
/** @return list<string> */
public function via(): array;
```

---

### NotifiableInterface (`src/NotifiableInterface.php`)

Single-method contract for the notification recipient:

```php
public function routeNotificationFor(string $channel): string|int;
```

Returns the delivery address for the given channel:
- `'mail'`      → email address (string)
- `'broadcast'` → channel name (string)
- `'database'`  → entity ID (int or string)

---

### ChannelInterface / QueuableChannelInterface

`ChannelInterface` is the base delivery contract (`send()`). `QueuableChannelInterface` extends it with `toJob()`, allowing the Notifier to dispatch a self-contained queue Job instead of calling `send()` directly.

`DatabaseChannel` intentionally does NOT implement `QueuableChannelInterface` — database writes are fast and do not benefit from queueing.

---

### Notifier (`src/Notifier.php`)

Core dispatcher. Channels are injected as `array<string, ChannelInterface>` at construction.

| Method | Behaviour |
|--------|-----------|
| `send()` | Checks `ShouldQueueInterface` + `QueueInterface`. If both: calls `toJob()` on queuable channels and pushes; calls `send()` on non-queuable channels synchronously. Otherwise delegates to `sendNow()`. |
| `sendNow()` | Iterates `via()`, resolves each channel by name, calls `send()`. Always synchronous. |

Unknown channel names throw `NotificationException`.

---

### Notification (`src/Notification.php`)

Static facade mirroring the `Mail` and `Broadcast` facades. Holds a `?Notifier` singleton wired by `NotificationServiceProvider::boot()`. Throws `RuntimeException` when called before `setNotifier()`.

---

### NotificationServiceProvider (`src/NotificationServiceProvider.php`)

`register()` binds `Notifier` lazily. Channels registered:

| Channel | Always registered | Notes |
|---------|-------------------|-------|
| `mail` | yes | Requires `MailServiceProvider` to be registered before using |
| `broadcast` | yes | Requires `BroadcastServiceProvider` to be registered before using |
| `database` | only if `DatabaseInterface` is bound | Wrapped in try/catch — omitted silently if not bound |

`QueueInterface` is also resolved in a try/catch — missing binding means synchronous-only mode.

`boot()` calls `Notification::setNotifier($this->app->make(Notifier::class))`.

---

### MailChannel (`src/Channel/MailChannel.php`)

Validates that the notification implements `ToMailInterface`, then calls `Mail::send($notification->toMail($notifiable))`. `toJob()` pre-resolves the `Mailable` and wraps it in `SendMailNotificationJob`.

---

### BroadcastChannel (`src/Channel/BroadcastChannel.php`)

Validates `ToBroadcastInterface`, then calls `Broadcast::to(channel, event, payload)`. `toJob()` pre-resolves all three values into `SendBroadcastNotificationJob`.

---

### DatabaseChannel (`src/Channel/DatabaseChannel.php`)

Validates `ToDatabaseInterface`. On first `send()` call, creates the `notifications` table via `CREATE TABLE IF NOT EXISTS` using driver-aware DDL (MySQL vs SQLite). Stores `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), and `created_at`. `read_at` is always `NULL` on insert — marking as read is application responsibility.

---

### SendMailNotificationJob / SendBroadcastNotificationJob (`src/Queue/`)

Both extend `EzPhp\Queue\Job` and implement `handle()` with zero parameters (Worker contract). All data needed for delivery is embedded at construction time, before serialisation. `Mailable` is PHP-serialisable. Broadcast data (string + array) is inherently serialisable.

---

## Design Decisions and Constraints

- **`QueuableChannelInterface` separates queueable channels from synchronous-only channels.** Rather than wrapping the entire `Notifier::send()` in a job (which would require the Notifier itself to be serialisable), each channel that supports async delivery produces its own self-contained Job. This decouples serialisation from orchestration.
- **Jobs embed pre-resolved data, not the original notification.** `toMail()` and `broadcastOn/As/With()` are called *before* the Job is pushed. The Worker never calls these methods. This avoids re-injecting dependencies into the notification inside the Worker.
- **`DatabaseChannel` is always synchronous.** Database writes are cheap and do not benefit from queueing. Forcing async would require the DatabaseChannel to carry a PDO instance that cannot survive PHP serialisation. Keeping it synchronous is simpler and correct.
- **`DatabaseChannel` auto-creates the table.** `CREATE TABLE IF NOT EXISTS` in `ensureTable()` runs exactly once per `DatabaseChannel` instance. This matches the `DatabaseDriver` approach in `ez-php/queue` and makes development zero-config. Production deployments can pre-create the table via a migration.
- **Optional dependencies via `try/catch` in the SP.** `DatabaseInterface` and `QueueInterface` are truly optional — a notification module that only uses `mail` and `broadcast` channels should not require a database connection. The `try/catch \Throwable` pattern is pragmatic given that `ContainerInterface` has no `has()` method.
- **`Notification` facade is fail-fast.** Missing `setNotifier()` throws `RuntimeException` immediately, mirroring `Mail` and `Broadcast`. Silent discards are worse than loud failures.
- **`routeNotificationFor()` returns `string|int`.** All three built-in channels need either a string address or an integer/string ID. This union type avoids `mixed` while accommodating all use cases.
- **No `Notifiable` trait or abstract base class.** Implementing `routeNotificationFor()` is the entire contract. Adding a trait or base class would couple application models to the module without benefit.

---

## Testing Approach

- **No external infrastructure required.** All tests run in-process.
- **`DatabaseChannelTest`** — Uses SQLite `:memory:` via plain `PDO`. No MySQL or Docker required.
- **`MailChannelTest`** — Injects `SpyMailer implements MailerInterface` via `Mail::setMailer()`. Uses `Mail::resetMailer()` in `tearDown()` to prevent state leaking. `SpyMailer` is a file-scope named class (not anonymous) to avoid PHPStan's `property.onlyWritten` check on reference-backed properties.
- **`BroadcastChannelTest`** — Uses `ArrayDriver` (real in-memory driver from `ez-php/broadcast`) via `Broadcast::setBroadcaster()`. Uses `Broadcast::resetBroadcaster()` in `tearDown()`.
- **`NotifierTest`** — All channels are anonymous-class or file-scope-class stubs. `SpyQueue implements QueueInterface` captures `push()` calls. No external infrastructure.
- **`NotificationTest`** — Tests the static facade (set/reset/delegate). Uses `Notification::resetNotifier()` in `setUp()` and `tearDown()`.
- **`#[CoversClass]` required** — `beStrictAboutCoverageMetadata=true` is set in `phpunit.xml`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| Marking notifications as read (read_at) | Application layer — query the notifications table directly |
| Unread notification count badges | Application layer |
| In-process domain events | `ez-php/events` |
| Push notifications (APNS, FCM) | Application layer or a future `ez-php/push` module |
| SMS / phone channel | Application layer |
| Slack / webhook channel | Application layer |
| Template rendering for notification bodies | `ez-php/view` (use in `toMail()`) |
| Notification preferences per user | Application layer |
| Batching / rate-limiting notifications | Application layer + `ez-php/rate-limiter` |
