<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Integration;

use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\QrCodeFactory;
use PHPdot\Totp\Otp\Totp;
use PHPdot\Totp\Qr\QrCodeBridge;
use PHPdot\Totp\Secret\Secret;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the optional phpdot/qrcode bridge end to end: a Totp's provisioning
 * URI is rendered to scannable image formats.
 */
final class QrBridgeTest extends TestCase
{
    private QrCodeBridge $bridge;
    private Totp $totp;

    protected function setUp(): void
    {
        if (! class_exists(QrCodeFactory::class)) {
            self::markTestSkipped('phpdot/qrcode is not installed.');
        }

        $this->bridge = new QrCodeBridge(new QrCodeFactory());
        $this->totp = new Totp(new Secret('12345678901234567890'));
    }

    public function test_svg_renders_well_formed_document(): void
    {
        $svg = $this->bridge->svg($this->totp, 'alice@example.com', 'phpdot');

        self::assertStringStartsWith('<?xml', $svg);
        self::assertNotFalse(simplexml_load_string($svg));
    }

    public function test_png_renders_with_signature(): void
    {
        $png = $this->bridge->png($this->totp, 'alice@example.com', 'phpdot');

        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
    }

    public function test_data_uri_carries_mime_type(): void
    {
        self::assertStringStartsWith(
            'data:image/png;base64,',
            $this->bridge->dataUri($this->totp, 'alice', 'phpdot', ImageFormat::Png),
        );
    }
}
