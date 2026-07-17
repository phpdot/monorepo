<?php

declare(strict_types=1);

/**
 * Optional bridge that renders an enrollment QR straight from a {@see Totp}.
 *
 * Requires the suggested `phpdot/qrcode` package. It is deliberately NOT marked
 * with a container attribute: the two packages stay decoupled, and a consumer
 * without `phpdot/qrcode` is never asked to resolve a `QrCodeFactory`. Wire it
 * manually where you need it: `new QrCodeBridge($qrCodeFactory)`.
 *
 * ECI is disabled because an `otpauth://` URI is pure ASCII, which maximises
 * scanner compatibility.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Qr;

use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\QrCodeBuilder;
use PHPdot\QrCode\QrCodeFactory;
use PHPdot\Totp\Otp\Totp;

final readonly class QrCodeBridge
{
    /**
     * Holds the QrCodeFactory used to render provisioning URIs.
     *
     * @param QrCodeFactory $qrCode
     */
    public function __construct(
        private QrCodeFactory $qrCode,
    ) {}

    /**
     * Render the enrollment QR as an SVG string.
     *
     * @param Totp $totp
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    public function svg(Totp $totp, string $account, string $issuer): string
    {
        return $this->builder($totp, $account, $issuer)->toSvg();
    }

    /**
     * Render the enrollment QR as PNG binary.
     *
     * @param Totp $totp
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    public function png(Totp $totp, string $account, string $issuer): string
    {
        return $this->builder($totp, $account, $issuer)->toPng();
    }

    /**
     * Render the enrollment QR as a base64 `data:` URI (SVG by default).
     *
     * @param ImageFormat $format
     * @param Totp $totp
     * @param string $account
     * @param string $issuer
     *
     * @return string
     */
    public function dataUri(Totp $totp, string $account, string $issuer, ImageFormat $format = ImageFormat::Svg): string
    {
        return $this->builder($totp, $account, $issuer)->toDataUri($format);
    }

    /**
     * Builds a QR builder for the generator's provisioning URI, with ECI disabled.
     *
     * @param Totp $totp
     * @param string $account
     * @param string $issuer
     *
     * @return QrCodeBuilder
     */
    private function builder(Totp $totp, string $account, string $issuer): QrCodeBuilder
    {
        return $this->qrCode->create($totp->provisioningUri($account, $issuer))->eci(false);
    }
}
