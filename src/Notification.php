<?php

declare(strict_types=1);

namespace EzPhp\Notification;

/**
 * Class Notification
 *
 * Static facade for the active Notifier singleton.
 * NotificationServiceProvider calls setNotifier() in boot(), so all static
 * methods are available after the application is bootstrapped.
 *
 * Usage:
 *   Notification::send($user, new WelcomeNotification());
 *   Notification::sendNow($user, new WelcomeNotification()); // bypass queue
 *
 * Testing:
 *   Notification::setNotifier($spy); // inject a test double
 *   // ... exercise code under test ...
 *   Notification::resetNotifier();   // tear down in tearDown()
 *
 * @package EzPhp\Notification
 */
final class Notification
{
    /**
     * @var Notifier|null Active notifier singleton; null before setNotifier() is called.
     */
    private static ?Notifier $notifier = null;

    /**
     * Replace (or initialise) the active notifier.
     *
     * @param Notifier $notifier
     *
     * @return void
     */
    public static function setNotifier(Notifier $notifier): void
    {
        self::$notifier = $notifier;
    }

    /**
     * Clear the active notifier. Call in test tearDown() to prevent state leaking.
     *
     * @return void
     */
    public static function resetNotifier(): void
    {
        self::$notifier = null;
    }

    /**
     * Send the notification — queued if the notification implements ShouldQueueInterface
     * and a queue driver is available, synchronous otherwise.
     *
     * @param NotifiableInterface   $notifiable
     * @param NotificationInterface $notification
     *
     * @throws \RuntimeException      When called before setNotifier().
     * @throws NotificationException  When a channel is unknown or misconfigured.
     *
     * @return void
     */
    public static function send(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        self::notifier()->send($notifiable, $notification);
    }

    /**
     * Send the notification synchronously, bypassing ShouldQueueInterface.
     *
     * @param NotifiableInterface   $notifiable
     * @param NotificationInterface $notification
     *
     * @throws \RuntimeException      When called before setNotifier().
     * @throws NotificationException  When a channel is unknown or misconfigured.
     *
     * @return void
     */
    public static function sendNow(NotifiableInterface $notifiable, NotificationInterface $notification): void
    {
        self::notifier()->sendNow($notifiable, $notification);
    }

    /**
     * Return the active Notifier or throw if uninitialised.
     *
     * @throws \RuntimeException
     *
     * @return Notifier
     */
    private static function notifier(): Notifier
    {
        if (self::$notifier === null) {
            throw new \RuntimeException(
                'Notification facade is not initialised. Add NotificationServiceProvider to your application.'
            );
        }

        return self::$notifier;
    }
}
