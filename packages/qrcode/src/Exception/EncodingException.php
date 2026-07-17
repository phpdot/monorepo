<?php

declare(strict_types=1);

/**
 * Thrown when content cannot be encoded into a QR symbol.
 *
 * Typical causes: the payload is too large for the requested error-correction
 * level or for a forced version, or the chosen character encoding cannot
 * represent the content.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Exception;

final class EncodingException extends QrCodeException {}
