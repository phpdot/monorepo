<?php

declare(strict_types=1);

/**
 * SMTP commands per RFC 5321.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\Enum;

/**
 * RFC 5321 Section 4.1: SMTP commands.
 */
enum SmtpCommand: string
{
    case Ehlo = 'EHLO';
    case Helo = 'HELO';
    case MailFrom = 'MAIL';
    case RcptTo = 'RCPT';
    case Data = 'DATA';
    case Rset = 'RSET';
    case Noop = 'NOOP';
    case Quit = 'QUIT';
    case StartTls = 'STARTTLS';
    case Auth = 'AUTH';

    /**
     * Parse a command verb from a raw line.
     */
    public static function fromLine(string $line): self|null
    {
        $verb = strtoupper(trim(explode(' ', $line, 2)[0]));

        return self::tryFrom($verb);
    }
}
