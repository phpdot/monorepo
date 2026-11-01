<?php

declare(strict_types=1);

/**
 * Decodes SASL authentication tokens for SMTP AUTH.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Auth;

use PHPdot\Mail\Gateway\DataType\DTO\AuthCredentials;
use PHPdot\Mail\Gateway\DataType\Enum\AuthMechanism;
use PHPdot\Mail\Gateway\Exception\AuthenticationException;
use PHPdot\Mail\Gateway\Exception\InvalidSaslSyntaxException;

/**
 * Decodes SASL tokens into AuthCredentials.
 *
 * - PLAIN (RFC 4616): base64(\0username\0password)
 * - LOGIN (draft): base64(username) then base64(password) in two steps
 * - XOAUTH2 (Google): base64(user=<user>\001auth=Bearer <token>\001\001)
 */
final class SaslDecoder
{
    /**
     * Decode AUTH PLAIN token.
     *
     * RFC 4616: message = [authzid] NUL authcid NUL passwd
     */
    public function decodePlain(string $base64Token): AuthCredentials
    {
        $decoded = base64_decode($base64Token, true);
        if ($decoded === false) {
            throw new \PHPdot\Mail\Gateway\Exception\InvalidSaslSyntaxException('Invalid base64 in AUTH PLAIN');
        }

        $parts = explode("\0", $decoded);
        if (count($parts) !== 3) {
            throw new InvalidSaslSyntaxException('Invalid AUTH PLAIN format: expected 3 NUL-separated parts');
        }

        $username = $parts[1];
        $password = $parts[2];

        if ($username === '' || $password === '') {
            throw new AuthenticationException('Empty username or password in AUTH PLAIN');
        }

        return new AuthCredentials(AuthMechanism::Plain, $username, $password);
    }

    /**
     * Decode AUTH LOGIN credentials (two-step: username then password).
     */
    public function decodeLogin(string $base64Username, string $base64Password): AuthCredentials
    {
        $username = base64_decode($base64Username, true);
        $password = base64_decode($base64Password, true);

        if ($username === false || $password === false) {
            throw new \PHPdot\Mail\Gateway\Exception\InvalidSaslSyntaxException('Invalid base64 in AUTH LOGIN');
        }

        if ($username === '' || $password === '') {
            throw new AuthenticationException('Empty username or password in AUTH LOGIN');
        }

        return new AuthCredentials(AuthMechanism::Login, $username, $password);
    }

    /**
     * Decode AUTH XOAUTH2 token.
     *
     * Format: base64("user=" user "\001auth=Bearer " token "\001\001")
     */
    public function decodeXOAuth2(string $base64Token): AuthCredentials
    {
        $decoded = base64_decode($base64Token, true);
        if ($decoded === false) {
            throw new \PHPdot\Mail\Gateway\Exception\InvalidSaslSyntaxException('Invalid base64 in AUTH XOAUTH2');
        }

        if (preg_match('/^user=([^\x01]+)\x01auth=Bearer ([^\x01]+)\x01\x01$/', $decoded, $m) !== 1) {
            throw new AuthenticationException('Invalid XOAUTH2 token format');
        }

        return new AuthCredentials(AuthMechanism::XOAuth2, $m[1], $m[2]);
    }
}
