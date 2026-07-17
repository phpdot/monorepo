<?php

declare(strict_types=1);

/**
 * The HMAC hash algorithm backing an OTP.
 *
 * The enum value is the exact name `hash_hmac()` expects, so the set is closed
 * to algorithms PHP always supports — no runtime validation is needed. SHA-1 is
 * the default because every authenticator app supports it; SHA-256/512 are
 * available but not universally honoured by scanner apps.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Enum;

enum Algorithm: string
{
    case Sha1 = 'sha1';
    case Sha256 = 'sha256';
    case Sha512 = 'sha512';

    /**
     * The upper-case label used in an `otpauth://` provisioning URI.
     *
     * @return string
     */
    public function label(): string
    {
        return strtoupper($this->value);
    }
}
