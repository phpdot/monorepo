<?php

declare(strict_types=1);

/**
 * RFC 4648 Base32 codec, used to exchange secrets with authenticator apps.
 *
 * Encoding produces upper-case output with no `=` padding (the convention for
 * `otpauth://` secrets). Decoding is tolerant: it accepts lower case, ignores
 * spaces and padding, and rejects any out-of-alphabet character.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Secret;

use PHPdot\Totp\Exception\InvalidSecretException;

final class Base32
{
    private const string ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Encode raw bytes to unpadded, upper-case Base32.
     *
     * @param string $bytes
     *
     * @return string
     */
    public static function encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($bytes) as $char) {
            $buffer = ($buffer << 8) | ord($char);
            $bitsLeft += 8;

            while ($bitsLeft >= 5) {
                $bitsLeft -= 5;
                $output .= self::ALPHABET[($buffer >> $bitsLeft) & 0x1f];
            }
        }

        if ($bitsLeft > 0) {
            $output .= self::ALPHABET[($buffer << (5 - $bitsLeft)) & 0x1f];
        }

        return $output;
    }

    /**
     * Decode Base32 (any case, padding and whitespace ignored) to raw bytes.
     *
     * @param string $base32
     *
     * @throws InvalidSecretException if a character is outside the Base32 alphabet
     *
     * @return string
     */
    public static function decode(string $base32): string
    {
        $clean = strtoupper((string) preg_replace('/[\s=]+/', '', $base32));

        if ($clean === '') {
            return '';
        }

        $map = array_flip(str_split(self::ALPHABET));

        $output = '';
        $buffer = 0;
        $bitsLeft = 0;

        foreach (str_split($clean) as $char) {
            if (! isset($map[$char])) {
                throw new InvalidSecretException("Invalid Base32 character: '{$char}'.");
            }

            $buffer = ($buffer << 5) | $map[$char];
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $output;
    }
}
