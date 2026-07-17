<?php

declare(strict_types=1);

/**
 * Entry point for generating QR codes — inject this into controllers/services.
 *
 * Holds the stateless encoder and renderers (all singletons) and hands out a
 * fresh {@see QrCodeBuilder} per call, so concurrent coroutines never share
 * mutable build state. For the common cases there are direct one-call helpers.
 *
 *     public function __construct(private QrCodeFactory $qr) {}
 *
 *     $svg = $this->qr->svg('https://phpdot.com');
 *     $png = $this->qr->create($token)->errorCorrection(ErrorCorrection::High)->toPng();
 *
 * Registered as a singleton; safe to share across coroutines.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\QrCode\Encoder\Encoder;
use PHPdot\QrCode\Enum\ImageFormat;
use PHPdot\QrCode\Renderer\DataUriRenderer;
use PHPdot\QrCode\Renderer\PngRenderer;
use PHPdot\QrCode\Renderer\SvgRenderer;

#[Singleton]
final readonly class QrCodeFactory
{
    /**
     * Holds the shared stateless encoder and renderers used to build QR codes.
     *
     * @param Encoder $encoder
     * @param SvgRenderer $svg
     * @param PngRenderer $png
     * @param DataUriRenderer $dataUri
     */
    public function __construct(
        private Encoder $encoder = new Encoder(),
        private SvgRenderer $svg = new SvgRenderer(),
        private PngRenderer $png = new PngRenderer(),
        private DataUriRenderer $dataUri = new DataUriRenderer(),
    ) {}

    /**
     * Start a fluent build, optionally seeding the content.
     *
     * @param string $data
     *
     * @return QrCodeBuilder
     */
    public function create(string $data = ''): QrCodeBuilder
    {
        return new QrCodeBuilder($this->encoder, $this->svg, $this->png, $this->dataUri, $data);
    }

    /**
     * Encode `$data` to a {@see QrCode} value object using the defaults.
     *
     * @param string $data
     *
     * @return QrCode
     */
    public function make(string $data): QrCode
    {
        return $this->create($data)->encode();
    }

    /**
     * One-call SVG render with default options.
     *
     * @param string $data
     *
     * @return string
     */
    public function svg(string $data): string
    {
        return $this->create($data)->toSvg();
    }

    /**
     * One-call PNG render with default options.
     *
     * @param string $data
     *
     * @return string
     */
    public function png(string $data): string
    {
        return $this->create($data)->toPng();
    }

    /**
     * One-call `data:` URI render with default options (SVG by default).
     *
     * @param string $data
     * @param ImageFormat $format
     *
     * @return string
     */
    public function dataUri(string $data, ImageFormat $format = ImageFormat::Svg): string
    {
        return $this->create($data)->toDataUri($format);
    }
}
