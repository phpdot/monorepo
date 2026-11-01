<?php

declare(strict_types=1);

/**
 * SMTP AUTH mechanisms per RFC 4954.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\DataType\Enum;

/**
 * Supported SASL authentication mechanisms.
 */
enum AuthMechanism: string
{
    case Plain = 'PLAIN';
    case Login = 'LOGIN';
    case XOAuth2 = 'XOAUTH2';

    public static function fromString(string $value): self|null
    {
        return self::tryFrom(strtoupper($value));
    }
}
