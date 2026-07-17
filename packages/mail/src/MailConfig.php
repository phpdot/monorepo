<?php

declare(strict_types=1);

/**
 * Mailer configuration, hydrated from `config/mail.php` by phpdot/package when
 * the `#[Config]` attribute is scanned. Works standalone via its defaults.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail;

use PHPdot\Container\Attribute\Config;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mail\Exception\MailException;

#[Singleton]
#[Config('mail')]
final readonly class MailConfig
{
    /**
     * Creates the mailer configuration, rejecting an empty DSN.
     *
     * @param string $dsn Transport DSN, e.g. "smtp://user:pass@smtp.example.com:587".
     *                    Defaults to the null transport (sends nowhere) so an
     *                    unconfigured install never errors. Read from `MAIL_DSN`.
     * @param string $fromEmail Default sender address used when a message sets no "from".
     * @param string $fromName Default sender display name.
     */
    public function __construct(
        public string $dsn = 'null://null',
        public string $fromEmail = '',
        public string $fromName = '',
    ) {
        if ($dsn === '') {
            throw new MailException('Mail DSN must not be empty.');
        }
    }
}
