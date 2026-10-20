<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Mail\Mailable;
use EzPhp\Mail\MailerInterface;

/**
 * Spy mailer that captures every sent Mailable.
 *
 * A named, autoloadable class (not anonymous, not file-scoped inside a test)
 * so any test can use it on its own and PHPStan's `property.onlyWritten`
 * check does not trip over a reference-backed property.
 *
 * @package Tests\Support
 */
final class NotificationSpyMailer implements MailerInterface
{
    /** @var list<Mailable> */
    private array $sent = [];

    /**
     * @param Mailable $mailable
     *
     * @return void
     */
    public function send(Mailable $mailable): void
    {
        $this->sent[] = $mailable;
    }

    /**
     * @return list<Mailable>
     */
    public function getSent(): array
    {
        return $this->sent;
    }
}
