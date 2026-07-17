<?php

declare(strict_types=1);

/**
 * Builds `otpauth://` provisioning URIs (the Key Uri Format authenticator apps
 * scan).
 *
 * The label is `Issuer:Account` with each part percent-encoded; the query is
 * RFC 3986-encoded (so spaces are `%20`, not `+`) and carries the issuer a second
 * time, as apps expect. The content is pure ASCII, so when rendered as a QR code
 * the ECI prefix should be disabled for the widest scanner compatibility.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Provisioning;

use PHPdot\Totp\Exception\InvalidParameterException;
use PHPdot\Totp\Otp\Hotp;
use PHPdot\Totp\Otp\Otp;
use PHPdot\Totp\Otp\Totp;

final class ProvisioningUri
{
    /**
     * Builds the `otpauth://totp/...` provisioning URI for a time-based generator.
     *
     * @param Totp $totp
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    public function totp(Totp $totp, string $account, string $issuer): string
    {
        return $this->build('totp', $totp, $account, $issuer, ['period' => $totp->period()]);
    }

    /**
     * Builds the `otpauth://hotp/...` provisioning URI for a counter-based generator.
     *
     * @param Hotp $hotp
     * @param string $account
     * @param string $issuer
     * @param int $counter
     *
     * @return string
     */
    public function hotp(Hotp $hotp, string $account, string $issuer, int $counter): string
    {
        return $this->build('hotp', $hotp, $account, $issuer, ['counter' => $counter]);
    }

    /**
     * Assembles an `otpauth://` URI from the shared and type-specific parameters.
     *
     * @param array<string, int> $extra Type-specific query parameters (period or counter).
     * @param string $type
     * @param Otp $otp
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    private function build(string $type, Otp $otp, string $account, string $issuer, array $extra): string
    {
        if ($issuer === '' || $account === '') {
            throw new InvalidParameterException('Provisioning URI requires a non-empty issuer and account.');
        }

        $params = [
            'secret' => $otp->secret()->toBase32(),
            'issuer' => $issuer,
            'algorithm' => $otp->algorithm()->label(),
            'digits' => $otp->digits(),
            ...$extra,
        ];

        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);

        return "otpauth://{$type}/{$label}?{$query}";
    }
}
