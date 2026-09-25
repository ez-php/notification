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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

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

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum`,
`opcache` → `OPCache`, and `dotenv` → `Env` are existing exceptions the guess
gets wrong; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
`webauthn-metadata` → `WebauthnMetadata` and `metrics-statsd` → `MetricsStatsd` are
intentional lower-case-word namespaces, and `testing-application` shares `EzPhp\Testing\`
with `testing`).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks every
  `CLAUDE.md` copy as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

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
│   ├── ToPushInterface.php            — pushToken/toPush; required for 'push' channel
│   ├── ToDatabaseInterface.php        — toDatabase(notifiable): array; required for 'database' channel
│   ├── MailChannel.php                — implements QueuableChannelInterface; calls Mail::send()
│   ├── BroadcastChannel.php           — implements QueuableChannelInterface; calls Broadcast::to()
│   ├── PushChannel.php                — implements QueuableChannelInterface; calls Push::send()
│   ├── DatabaseChannel.php            — implements ChannelInterface; inserts into notifications table (auto-created)
│   └── RateLimitedChannel.php         — implements ChannelInterface; decorator throttling delivery through another channel via ez-php/rate-limiter (soft dependency — require-dev only)
└── Queue/
    ├── SendMailNotificationJob.php    — Job storing pre-built Mailable; handle() calls Mail::send()
    ├── SendBroadcastNotificationJob.php — Job storing channel/event/payload; handle() calls Broadcast::to()
    └── SendPushNotificationJob.php    — Job storing token/PushMessage; handle() calls Push::send()

tests/
├── TestCase.php                       — base PHPUnit test case
├── NotifierTest.php                   — covers Notifier: sync dispatch, multi-channel, queuing, sendNow bypass
├── NotificationTest.php               — covers Notification facade: delegation, uninitialized throw, reset, replace
└── Channel/
    ├── MailChannelTest.php            — covers MailChannel + SendMailNotificationJob
    ├── BroadcastChannelTest.php       — covers BroadcastChannel + SendBroadcastNotificationJob
    ├── PushChannelTest.php            — covers PushChannel + SendPushNotificationJob
    ├── DatabaseChannelTest.php        — covers DatabaseChannel with SQLite :memory: (no MySQL required)
    └── RateLimitedChannelTest.php     — covers RateLimitedChannel: delivery under limit, drop over limit, per-key isolation via ez-php/rate-limiter's ArrayDriver
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
- `'push'`      → device token (string)
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
| `push` | yes | Requires `PushServiceProvider` to be registered before using |
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

### PushChannel (`src/Channel/PushChannel.php`)

Validates `ToPushInterface`, then calls `Push::send(token, message)` with the device token and `PushMessage` returned by the notification's `pushToken()`/`toPush()` methods. `toJob()` pre-resolves both into `SendPushNotificationJob`.

---

### DatabaseChannel (`src/Channel/DatabaseChannel.php`)

Validates `ToDatabaseInterface`. On first `send()` call, creates the `notifications` table via `CREATE TABLE IF NOT EXISTS` using driver-aware DDL (MySQL vs SQLite). Stores `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), and `created_at`. `read_at` is always `NULL` on insert — marking as read is application responsibility.

---

### RateLimitedChannel (`src/Channel/RateLimitedChannel.php`)

Decorator implementing `ChannelInterface` (not `QueuableChannelInterface` — it wraps whatever channel is passed to it and forwards `send()` only; queueing is the wrapped channel's own concern, orchestrated by `Notifier` before this decorator is ever reached). Wraps another `ChannelInterface`, a `RateLimiterInterface`, and a required `Closure(NotifiableInterface, NotificationInterface): string $keyResolver`. `send()` calls `$limiter->attempt(key, maxAttempts, decaySeconds)`; on success it forwards to the wrapped channel, on throttle it silently returns without delivering.

---

### SendMailNotificationJob / SendBroadcastNotificationJob / SendPushNotificationJob (`src/Queue/`)

All three extend `EzPhp\Queue\Job` and implement `handle()` with zero parameters (Worker contract). All data needed for delivery is embedded at construction time, before serialisation. `Mailable` is PHP-serialisable. Broadcast data (string + array) is inherently serialisable. `PushMessage` is a `readonly` value object of scalars/arrays and is inherently serialisable too.

---

## Design Decisions and Constraints

- **`QueuableChannelInterface` separates queueable channels from synchronous-only channels.** Rather than wrapping the entire `Notifier::send()` in a job (which would require the Notifier itself to be serialisable), each channel that supports async delivery produces its own self-contained Job. This decouples serialisation from orchestration.
- **Jobs embed pre-resolved data, not the original notification.** `toMail()` and `broadcastOn/As/With()` are called *before* the Job is pushed. The Worker never calls these methods. This avoids re-injecting dependencies into the notification inside the Worker.
- **`DatabaseChannel` is always synchronous.** Database writes are cheap and do not benefit from queueing. Forcing async would require the DatabaseChannel to carry a PDO instance that cannot survive PHP serialisation. Keeping it synchronous is simpler and correct.
- **`DatabaseChannel` auto-creates the table.** `CREATE TABLE IF NOT EXISTS` in `ensureTable()` runs exactly once per `DatabaseChannel` instance. This matches the `DatabaseDriver` approach in `ez-php/queue` and makes development zero-config. Production deployments can pre-create the table via a migration.
- **Optional bindings via `has()` in the SP.** `DatabaseInterface` and `QueueInterface` are optional *at runtime* — a notification setup that only uses `mail` and `broadcast` channels should not require a database connection, and without a bound `QueueInterface` every notification is sent synchronously. `NotificationServiceProvider` checks `ContainerInterface::has()` before resolving either. `ez-php/queue` itself is still a hard `require` (the `Queue\*Job` classes extend `EzPhp\Queue\Job`), so "optional" refers to the binding, not to the installed package.
- **`Notification` facade is fail-fast.** Missing `setNotifier()` throws `RuntimeException` immediately, mirroring `Mail` and `Broadcast`. Silent discards are worse than loud failures.
- **`RateLimitedChannel` requires an explicit `$keyResolver`, with no default.** `NotifiableInterface` only exposes identity per-channel via `routeNotificationFor(string $channel)`, and `NotificationInterface` carries no id of its own — there is no generically-correct key to guess (unlike `ThrottleMiddleware`, which can default to the client IP from the `Request` it's always given). The caller must supply one.
- **`RateLimitedChannel` drops on throttle, it does not queue or retry.** This matches `ThrottleMiddleware` returning 429 rather than buffering the request — the decorator's job is to cap delivery rate, not to guarantee eventual delivery. An application that wants "deliver later instead of drop" should combine this with a `QueuableChannelInterface` channel and its own backoff logic.
- **`ez-php/rate-limiter` is a soft dependency, `require-dev` only** — same reasoning as `ez-php/mail`'s `Job\SendMailableJob`: a module that pulls in a package as a hard `require` forces it on every consumer, even ones that never use the decorator. PSR-4 only resolves `RateLimitedChannel.php` (and therefore `RateLimiterInterface`) when something actually references the class.
- **`routeNotificationFor()` returns `string|int`.** All built-in channels need either a string address or an integer/string ID. This union type avoids `mixed` while accommodating all use cases.
- **`PushChannel` depends on `ez-php/push`'s `Push` facade, not the container.** This mirrors `MailChannel`/`BroadcastChannel`, which likewise call their module's static facade (`Mail::send()`, `Broadcast::to()`) rather than resolving a service from the DI container — the facade is the module's public API. `ez-php/push` is a hard `require` of this package, not optional, matching `ez-php/mail` and `ez-php/broadcast`.
- **No `Notifiable` trait or abstract base class.** Implementing `routeNotificationFor()` is the entire contract. Adding a trait or base class would couple application models to the module without benefit.

---

## Testing Approach

- **No external infrastructure required.** All tests run in-process.
- **`DatabaseChannelTest`** — Uses SQLite `:memory:` via plain `PDO`. No MySQL or Docker required.
- **`MailChannelTest`** — Injects `SpyMailer implements MailerInterface` via `Mail::setMailer()`. Uses `Mail::resetMailer()` in `tearDown()` to prevent state leaking. `SpyMailer` is a file-scope named class (not anonymous) to avoid PHPStan's `property.onlyWritten` check on reference-backed properties.
- **`BroadcastChannelTest`** — Uses `ArrayDriver` (real in-memory driver from `ez-php/broadcast`) via `Broadcast::setBroadcaster()`. Uses `Broadcast::resetBroadcaster()` in `tearDown()`.
- **`PushChannelTest`** — Uses `ArrayDriver` (real in-memory driver from `ez-php/push`) via `Push::setPusher(new Pusher($driver))`. Uses `Push::resetPusher()` in `tearDown()`.
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
| SMS / phone channel | Application layer |
| Slack / webhook channel | Application layer |
| Template rendering for notification bodies | `ez-php/view` (use in `toMail()`) |
| Notification preferences per user | Application layer |
| Batching of notifications | Application layer |
| Rate-limiting beyond `Channel\RateLimitedChannel`'s single-channel decorator | Application layer builds more elaborate throttling (per-notification-type budgets, cross-channel limits, etc.) on top of it |
