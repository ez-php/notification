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
3. `check_module_deps.php` — fails when a package's code imports an ez-php package its `composer.json` does not declare (module `src`: `require`/`suggest`; tests: `require`/`require-dev` and their dependencies), or requires one it never uses
4. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
5. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
6. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
composer module-deps:check   # undeclared / unused ez-php package dependencies only
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
php make_module.php <name> --description="..." --services=mysql,redis --extensions=gmp
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` (each `-`-separated word upper-cased) unless `--namespace=`
overrides it. Existing exceptions the guess gets wrong: `asyncapi` → `AsyncApi`,
`bignum` → `BigNum`, `dataloader` → `DataLoader`, `dotenv` → `Env`, `graphql` → `GraphQL`, `graphql-subscriptions` → `GraphQLSubscriptions`, `oauth` → `OAuth`,
`opcache` → `OPCache`, `openapi` → `OpenApi`, `qrcode` → `QrCode`, `swagger-ui` → `SwaggerUI`, `webauthn` → `WebAuthn` and
`websocket` → `WebSocket`; `websocket-client` → `WebsocketClient`, `websocket-tls` → `WebsocketTls`,
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
with `--services`/`--extensions` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4` **and** the shared
`autoload-dev` `Tests\` directory list), `phpstan.neon`, `phpunit.xml` (test suite
**and** coverage source), and `packages.sh` (alphabetical position) — in both
generated and `--repo` mode.

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — claim the "next free" row by
  editing the table in `CODING_GUIDELINES.md` (never in a `CLAUDE.md` copy) and run
  `composer guidelines:sync` in the same change. Editing it drifts every `CLAUDE.md`
  until the sync runs, which is why the generator only reminds you instead of doing
  it. Skipping the edit leaves "next free" stale, so the next module collides.

### 4 — Docker scaffold

Run from the new module root (requires `ez-php/docker` in `require-dev` — like every ez-php requirement, constrained to the current release, e.g. `"^2.6.0"`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders (`docker/db/`, the MySQL init script, only with `--services=mysql`). Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch/Memcached/Mailpit service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
vendor/bin/docker-init --services=memcached,mailpit
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`, `memcached`, `apcu` (with `apc.enable_cli=1`):

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
vendor/bin/docker-init --extensions=memcached,apcu
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
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | 7702 (opt-in service) |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| `ez-php/broadcast` | — | 6384 (`REDIS_HOST_PORT`) | — |
| `ez-php/feature-flags` | — | 6385 (`REDIS_HOST_PORT`) | — |
| `ez-php/scheduler` | — | 6386 (`REDIS_HOST_PORT`) | — |
| `ez-php/session` | — | 6387 (`REDIS_HOST_PORT`) | — |
| `ez-php/metrics` | — | 6388 (`REDIS_HOST_PORT`) | — |
| `ez-php/event-store-snapshots` | 3312 | — | — |
| **next free** | **3313** | **6389** | **7703** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. Every module row maps it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT`, `HEALTH_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. Mailpit is the one other service with published host ports: SMTP `1025` and web UI `8025`. `ez-php/mail` maps them through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above, documented in `modules/mail/.env.example`); the root project and the `ez-php/` template each run their own Mailpit on the same defaults (`MAIL_PORT`/`MAIL_WEB_PORT`), so **these three stacks cannot run at the same time** without overriding those variables. It isn't a table column because no module beyond those three runs Mailpit — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here. Services reached only over the Compose network publish no host port and need no entry at all: Memcached (`memcached:11211` in the root stack and `ez-php/cache`) and the opt-in Elasticsearch/Typesense backends in `modules/search/docker-compose.ci.yml`.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Every multi-package script sources it — `update_all.sh`, `fullcheck.sh`, `bump_version.sh` and the `git_*_all.sh` scripts (`git_push_all.sh`, `git_pull_all.sh`, `git_tag_all.sh`, `git_delete_all_tags.sh`) — so the package list lives in exactly one place.

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
│   ├── ToSmsInterface.php             — smsTo/toSms; required for 'sms' channel
│   ├── ToDatabaseInterface.php        — toDatabase(notifiable): array; required for 'database' channel
│   ├── MailChannel.php                — implements QueuableChannelInterface; injected MailerInterface, Mail::send() fallback
│   ├── BroadcastChannel.php           — implements QueuableChannelInterface; publishes via the injected Broadcaster (Broadcast::to() fallback)
│   ├── PushChannel.php                — implements QueuableChannelInterface; injected Pusher, Push::send() fallback
│   ├── SmsChannel.php                 — implements QueuableChannelInterface; injected ez-php/sms SmsSenderInterface; soft dependency
│   ├── WebhookChannel.php             — ToWebhookInterface → ez-php/webhook WebhookDispatcher (signed, queued, retried); soft dependency
│   ├── ToWebhookInterface.php         — webhookUrl() / webhookSecret() / toWebhook() payload
│   ├── DatabaseChannel.php            — implements ChannelInterface; inserts into notifications table (auto-created)
│   ├── DatabaseNotificationRepository.php — read state over DatabaseInterface: unreadFor(), unreadCount(), markAsRead(), markAllAsRead(); scoped to the recipient
│   └── RateLimitedChannel.php         — implements ChannelInterface; decorator throttling delivery through another channel via ez-php/rate-limiter (soft dependency — require-dev only)
└── Queue/
    ├── SendMailNotificationJob.php    — Job storing pre-built Mailable; handle() calls Mail::send()
    ├── SendBroadcastNotificationJob.php — Job storing channel/event/payload; handle() calls Broadcast::to()
    ├── SendPushNotificationJob.php    — Job storing token/PushMessage; handle() calls Push::send()
    └── SendSmsNotificationJob.php     — Job storing number/SmsMessage; handle() calls Sms::send()

tests/
├── TestCase.php                       — base PHPUnit test case
├── Queue/NotificationQueueJobRoundTripTest.php  — queued jobs holding Mailable/PushMessage/SmsMessage survive a queue push/pop
├── NotifierTest.php                   — covers Notifier: sync dispatch, multi-channel, queuing, sendNow bypass
├── NotificationTest.php               — covers Notification facade: delegation, uninitialized throw, reset, replace
├── NotificationServiceProviderTest.php — register/boot smoke test; the mail and broadcast channels use the bound MailerInterface / Broadcaster
├── Support/NotificationSpyMailer.php  — recording MailerInterface shared by the mail-channel and provider tests
├── Support/NotificationPdoDatabase.php — DatabaseInterface over an existing PDO, so a test can share one in-memory SQLite connection
├── Support/FakeConfig.php             — minimal ConfigInterface stub for service provider tests
├── Support/FakeContainer.php          — minimal ContainerInterface stub for service provider tests
└── Channel/
    ├── MailChannelTest.php            — covers MailChannel + SendMailNotificationJob
    ├── BroadcastChannelTest.php       — covers BroadcastChannel + SendBroadcastNotificationJob
    ├── PushChannelTest.php            — covers PushChannel + SendPushNotificationJob
    ├── SmsChannelTest.php             — covers SmsChannel + SendSmsNotificationJob via ez-php/sms's ArrayDriver
    ├── DatabaseChannelTest.php        — covers DatabaseChannel with SQLite :memory: (no MySQL required)
    ├── DatabaseNotificationRepositoryTest.php  — unreadFor() order/limit, markAsRead()/markAllAsRead() scoped to the recipient, unreadCount()
    ├── WebhookChannelTest.php  — dispatches a signed delivery job; rejects notifications without ToWebhookInterface
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
- `'sms'`       → phone number, E.164 (string)
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
| `sms` | only if `SmsSenderInterface` is bound | Requires ez-php/sms's `SmsServiceProvider` |

`QueueInterface` is also resolved in a try/catch — missing binding means synchronous-only mode.

`boot()` calls `Notification::setNotifier($this->app->make(Notifier::class))`.

---

### MailChannel (`src/Channel/MailChannel.php`)

Validates that the notification implements `ToMailInterface`, then sends `$notification->toMail($notifiable)` through the constructor-injected `?MailerInterface` — or, constructed without one, `Mail::send()`. `toJob()` pre-resolves the `Mailable` and wraps it in `SendMailNotificationJob`.

---

### BroadcastChannel (`src/Channel/BroadcastChannel.php`)

Validates `ToBroadcastInterface`, then calls `to(channel, event, payload)` on the injected `Broadcaster` (constructor argument, `null` = the `Broadcast::to()` façade). `toJob()` pre-resolves all three values into `SendBroadcastNotificationJob`.

---

### WebhookChannel (`src/Channel/WebhookChannel.php`)

Validates `ToWebhookInterface` and hands URL, payload and secret to `ez-php/webhook`'s `WebhookDispatcher`, which signs the JSON body and queues a retrying `DeliverWebhookJob`. Deliberately not a `QueuableChannelInterface` — the dispatcher already queues. `ez-php/webhook` is a `suggest` (+ `require-dev`); `NotificationServiceProvider` registers the `webhook` channel only when `WebhookDispatcher` is bound. Slack/Teams incoming webhooks are just a URL and a payload shape (an empty secret is fine — they don't verify signatures).

### PushChannel (`src/Channel/PushChannel.php`)

Validates `ToPushInterface`, then sends through the constructor-injected `?Pusher` (fallback: `Push::send()`) the device token and `PushMessage` returned by the notification's `pushToken()`/`toPush()` methods. `toJob()` pre-resolves both into `SendPushNotificationJob`.

### SmsChannel (`src/Channel/SmsChannel.php`)

Validates `ToSmsInterface`, then sends the phone number and `SmsMessage` returned by `smsTo()`/`toSms()` through the constructor-injected `SmsSenderInterface` (required — no façade fallback, since the channel is only registered when the sender is bound). `toJob()` pre-resolves both into `SendSmsNotificationJob`, whose `handle()` uses the `Sms` façade. `ez-php/sms` is a `suggest` (+ `require-dev`).

---

### DatabaseChannel (`src/Channel/DatabaseChannel.php`)

Validates `ToDatabaseInterface`. On first `send()` call, creates the `notifications` table via `CREATE TABLE IF NOT EXISTS` using driver-aware DDL (MySQL vs SQLite). Stores `type`, `notifiable_type`, `notifiable_id`, `data` (JSON), and `created_at`. `read_at` is always `NULL` on insert; `DatabaseNotificationRepository` reads and sets it.

### DatabaseNotificationRepository (`src/Channel/DatabaseNotificationRepository.php`)

Read-state helpers over `DatabaseInterface` (autowirable): `unreadFor($notifiable, $limit = 50)` (newest first, `data` JSON-decoded), `unreadCount()`, `markAsRead($notifiable, $id)` and `markAllAsRead()`. Every query filters on the recipient's `notifiable_type`/`notifiable_id` (derived exactly as `DatabaseChannel` stores them), so an id belonging to another recipient is never read or marked — `markAsRead()` just returns `false`.

---

### RateLimitedChannel (`src/Channel/RateLimitedChannel.php`)

Decorator implementing `ChannelInterface` (not `QueuableChannelInterface` — it wraps whatever channel is passed to it and forwards `send()` only; queueing is the wrapped channel's own concern, orchestrated by `Notifier` before this decorator is ever reached). Wraps another `ChannelInterface`, a `RateLimiterInterface`, and a required `Closure(NotifiableInterface, NotificationInterface): string $keyResolver`. `send()` calls `$limiter->attempt(key, maxAttempts, decaySeconds)`; on success it forwards to the wrapped channel, on throttle it silently returns without delivering.

---

### SendMailNotificationJob / SendBroadcastNotificationJob / SendPushNotificationJob / SendSmsNotificationJob (`src/Queue/`)

All four extend `EzPhp\Queue\Job` and implement `handle()` with zero parameters (Worker contract). All data needed for delivery is embedded at construction time, before serialisation. `Mailable` is PHP-serialisable. Broadcast data (string + array) is inherently serialisable. `PushMessage` and `SmsMessage` are `readonly` value objects of scalars/arrays and are inherently serialisable too.

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
- **`ez-php/sms` is a soft dependency (`suggest` + `require-dev`)**, like `ez-php/webhook`, not a hard `require` like `ez-php/push`: SMS needs a paid provider account most applications don't have, so it is not forced on every consumer. The provider registers the `sms` channel only when `interface_exists(SmsSenderInterface::class)` and the container has a binding — PSR-4 never loads `SmsChannel` otherwise.
- **`routeNotificationFor()` returns `string|int`.** All built-in channels need either a string address or an integer/string ID. This union type avoids `mixed` while accommodating all use cases.
- **`MailChannel`/`BroadcastChannel`/`PushChannel` take their delivery service by constructor injection, with the façade as fallback.** `NotificationServiceProvider` passes the bound `MailerInterface` / `Broadcaster` / `Pusher` when the container has one, like `WebhookChannel` (`WebhookDispatcher`) and `DatabaseChannel` (`PDO`); a channel built without one (`new MailChannel()`, or no binding) calls `Mail::send()` / `Broadcast::to()` / `Push::send()` as before. Synchronous delivery therefore no longer depends on the façades having been wired first, and tests can inject a fake instead of setting global state. The queued `Send*NotificationJob`s still use the façades — a serialized job cannot carry the service. `ez-php/push` is a hard `require` of this package, not optional, matching `ez-php/mail` and `ez-php/broadcast`.
- **No `Notifiable` trait or abstract base class.** Implementing `routeNotificationFor()` is the entire contract. Adding a trait or base class would couple application models to the module without benefit.

---

## Testing Approach

- **No external infrastructure required.** All tests run in-process.
- **`DatabaseChannelTest`** — Uses SQLite `:memory:` via plain `PDO`. No MySQL or Docker required.
- **`MailChannelTest`** — Injects `Support\NotificationSpyMailer implements MailerInterface` via `Mail::setMailer()` for the façade fallback, and directly into the constructor for the injected path (asserting the façade mailer stays untouched). Uses `Mail::resetMailer()` in `tearDown()` to prevent state leaking. The spy is a named, autoloadable class in `tests/Support/` (not anonymous, and not file-scoped inside one test) so `NotificationServiceProviderTest` can use it when run on its own, and PHPStan's `property.onlyWritten` check does not trip over a reference-backed property.
- **`BroadcastChannelTest`** — Uses `ArrayDriver` (real in-memory driver from `ez-php/broadcast`), both injected into the channel and through `Broadcast::setBroadcaster()` for the façade fallback. Uses `Broadcast::resetBroadcaster()` in `tearDown()`.
- **`PushChannelTest`** — Uses `ArrayDriver` (real in-memory driver from `ez-php/push`) via `Push::setPusher(new Pusher($driver))`, and a second `ArrayDriver` behind a constructor-injected `Pusher` for the injected path. Uses `Push::resetPusher()` in `tearDown()`.
- **`SmsChannelTest`** — Uses `ez-php/sms`'s `ArrayDriver`, injected for `send()` and set via `Sms::setSender()` for the queued job's façade path. Uses `Sms::resetSender()` in `tearDown()`.
- **`NotifierTest`** — All channels are anonymous-class or file-scope-class stubs. `SpyQueue implements QueueInterface` captures `push()` calls. No external infrastructure.
- **`NotificationTest`** — Tests the static facade (set/reset/delegate). Uses `Notification::resetNotifier()` in `setUp()` and `tearDown()`.
- **`#[CoversClass]` required** — `beStrictAboutCoverageMetadata=true` is set in `phpunit.xml`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---------|-----------------|
| Notification preferences, read-state UI or real-time unread badges | Application layer (`DatabaseNotificationRepository` covers the queries) |
| Unread notification count badges | Application layer |
| In-process domain events | `ez-php/events` |
| SMS provider drivers (Twilio, Vonage) | `ez-php/sms` — this module only adapts its `SmsSenderInterface` as the `sms` channel |
| Slack SDK / Block Kit builders | Application layer — Slack is a `WebhookChannel` URL + payload |
| Template rendering for notification bodies | `ez-php/view` (use in `toMail()`) |
| Notification preferences per user | Application layer |
| Batching of notifications | Application layer |
| Rate-limiting beyond `Channel\RateLimitedChannel`'s single-channel decorator | Application layer builds more elaborate throttling (per-notification-type budgets, cross-channel limits, etc.) on top of it |
