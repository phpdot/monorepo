<?php

declare(strict_types=1);

/**
 * An encoded QR symbol: its module matrix plus the parameters chosen to build
 * it.
 *
 * This is a pure value object produced by the {@see \PHPdot\QrCode\Encoder\Encoder}.
 * It carries no rendering behaviour — pass its `matrix` to a renderer to obtain
 * SVG, PNG, or a data URI.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode;

use PHPdot\QrCode\Enum\ErrorCorrection;

final readonly class QrCode
{
    /**
     * Holds an encoded symbol's matrix alongside the parameters used to build it.
     *
     * @param Matrix $matrix
     * @param int $version
     * @param ErrorCorrection $errorCorrection
     * @param int $maskPattern
     * @param string $mode
     */
    public function __construct(
        public Matrix $matrix,
        public int $version,
        public ErrorCorrection $errorCorrection,
        public int $maskPattern,
        public string $mode,
    ) {}
}
