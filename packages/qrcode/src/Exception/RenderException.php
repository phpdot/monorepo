<?php

declare(strict_types=1);

/**
 * Thrown when a renderer cannot turn a matrix into its output format.
 *
 * Typical causes: the GD extension is missing or a GD call fails while
 * producing a PNG.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Exception;

final class RenderException extends QrCodeException {}
