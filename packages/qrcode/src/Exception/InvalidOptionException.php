<?php

declare(strict_types=1);

/**
 * A rendering option or color value is outside its valid domain.
 *
 * Raised at construction — malformed hex strings, channel values outside
 * 0-255, out-of-range sizes or margins — so a bad configuration fails where
 * it is written, not at render time.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Exception;

final class InvalidOptionException extends QrCodeException {}
