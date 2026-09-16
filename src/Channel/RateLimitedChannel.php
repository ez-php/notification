<?php

declare(strict_types=1);

namespace EzPhp\Notification\Channel;

use Closure;
use EzPhp\Notification\ChannelInterface;
use EzPhp\Notification\NotifiableInterface;
use EzPhp\Notification\NotificationInterface;
use EzPhp\RateLimiter\RateLimiterInterface;

/**
 * Class RateLimitedChannel
 *
 * Decorator that throttles delivery through another ChannelInterface using
 * ez-php/rate-limiter. When the limit is exceeded, the wrapped channel is
 * simply not called — the notification is dropped for this attempt, not
 * queued or retried, mirroring the "drop, don't error" convention used
 * throughout ez-php/rate-limiter (e.g. ThrottleMiddleware returns 429
 * rather than executing the handler).
 *
 * Requires: ez-php/rate-limiter (soft dependency — require-dev only; this
 * class is only autoloaded when actually referenced).
 *
 * There is no sensible default throttle key — NotifiableInterface exposes
 * identity only per-channel via routeNotificationFor(string $channel), and
 * NotificationInterface carries no id of its own — so $keyResolver is
 * required, not optional with a guessed default.
 *
 * @package EzPhp\Notification\Channel
 */
final class RateLimitedChannel implements ChannelInterface
{
    /**
     * RateLimitedChannel Constructor
     *
     * @param ChannelInterface                                            $channel      The channel to throttle.
     * @param RateLimiterInterface                                        $limiter
     * @param int                                                         $maxAttempts  Deliveries allowed per window.
     * @param int                                                         $decaySeconds Window length in seconds.
     * @param Closure(NotifiableInterface, NotificationInterface): string $keyResolver  Builds the rate-limit key.
     */
    public function __construct(
        private readonly ChannelInterface $channel,
        private readonly RateLimiterInterface $limiter,
        private readonly int $maxAttempts,
        private readonly int $decaySeconds,
        private readonly Closure $keyResolver,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        $key = ($this->keyResolver)($notifiable, $notification);

        if (!$this->limiter->attempt($key, $this->maxAttempts, $this->decaySeconds)) {
            return;
        }

        $this->channel->send($notifiable, $notification);
    }
}
