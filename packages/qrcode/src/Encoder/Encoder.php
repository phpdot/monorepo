<?php

declare(strict_types=1);

/**
 * Encodes content into a {@see QrCode}, wrapping the Bacon QR Code library.
 *
 * This is the single boundary between the package and Bacon: it translates our
 * {@see ErrorCorrection} enum into Bacon's level, runs the encoder, and copies
 * the resulting byte matrix into our own immutable {@see Matrix}. Nothing else
 * in the package references Bacon, so swapping the backend means changing only
 * this class.
 *
 * Bacon chooses the most compact mode (numeric → alphanumeric → byte → Kanji)
 * and the smallest version that fits, unless a version is forced.
 *
 * Stateless and safe to share across coroutines — registered as a singleton.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Encoder;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Common\Version;
use BaconQrCode\Encoder\ByteMatrix;
use BaconQrCode\Encoder\Encoder as BaconEncoder;
use BaconQrCode\Exception\ExceptionInterface as BaconException;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\QrCode\Enum\ErrorCorrection;
use PHPdot\QrCode\Exception\EncodingException;
use PHPdot\QrCode\Matrix;
use PHPdot\QrCode\QrCode;

#[Singleton]
final class Encoder
{
    /**
     * Default byte-mode character encoding. UTF-8 covers any input; an ECI
     * segment is added for byte mode so conformant scanners interpret the
     * bytes correctly.
     */
    public const string DEFAULT_ENCODING = 'UTF-8';

    /**
     * Encode `$data` into a QR symbol.
     *
     * @param string $data The content to encode.
     * @param ErrorCorrection $level Damage-recovery level (default medium).
     * @param string $encoding Byte-mode character set (default UTF-8).
     * @param int|null $forceVersion Force a symbol version 1–40, or null to auto-size.
     * @param bool $eci Prepend an ECI segment for non-Latin byte-mode encodings.
     *
     * @throws EncodingException if the content cannot be encoded (e.g. too large
     *                           for the level or the forced version)
     *
     * @return QrCode
     */
    public function encode(
        string $data,
        ErrorCorrection $level = ErrorCorrection::Medium,
        string $encoding = self::DEFAULT_ENCODING,
        int|null $forceVersion = null,
        bool $eci = true,
    ): QrCode {
        try {
            $forced = $forceVersion !== null ? Version::getVersionForNumber($forceVersion) : null;

            $encoded = BaconEncoder::encode(
                $data,
                $this->level($level),
                $encoding,
                $forced,
                $eci,
            );
        } catch (BaconException $e) {
            throw new EncodingException($e->getMessage(), $e->getCode(), $e);
        }

        return new QrCode(
            $this->matrix($encoded->getMatrix()),
            $encoded->getVersion()->getVersionNumber(),
            $level,
            $encoded->getMaskPattern(),
            (string) $encoded->getMode(),
        );
    }

    /**
     * Maps the package's error-correction enum onto Bacon's level object.
     *
     * @param ErrorCorrection $level
     *
     * @return ErrorCorrectionLevel
     */
    private function level(ErrorCorrection $level): ErrorCorrectionLevel
    {
        return match ($level) {
            ErrorCorrection::Low => ErrorCorrectionLevel::L(),
            ErrorCorrection::Medium => ErrorCorrectionLevel::M(),
            ErrorCorrection::Quartile => ErrorCorrectionLevel::Q(),
            ErrorCorrection::High => ErrorCorrectionLevel::H(),
        };
    }

    /**
     * Copies Bacon's byte matrix into an immutable grid of dark/light modules.
     *
     * @param ByteMatrix $bytes
     *
     * @return Matrix
     */
    private function matrix(ByteMatrix $bytes): Matrix
    {
        $size = $bytes->getWidth();

        $rows = [];
        for ($y = 0; $y < $size; ++$y) {
            $row = [];
            for ($x = 0; $x < $size; ++$x) {
                $row[] = $bytes->get($x, $y) === 1;
            }
            $rows[] = $row;
        }

        return new Matrix($rows, $size);
    }
}
