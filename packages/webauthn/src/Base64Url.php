<?php

declare(strict_types=1);

/**
 * The package's binary-to-text edge: challenges, credential ids, and user
 * handles cross every seam as unpadded base64url, matching what the browser
 * sends and the library expects. One place, so no call site picks the wrong
 * alphabet.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\WebAuthn;

use ParagonIE\ConstantTime\Base64UrlSafe;

final class Base64Url
{
    public static function encode(string $bytes): string
    {
        return Base64UrlSafe::encodeUnpadded($bytes);
    }

    public static function decode(string $encoded): string
    {
        return Base64UrlSafe::decode($encoded);
    }
}
