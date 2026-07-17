<?php

declare(strict_types=1);

/**
 * Base exception for the QR code package.
 *
 * Every exception thrown by this package extends this type, so a consumer can
 * catch `QrCodeException` to trap any failure originating here — encoding or
 * rendering — without catching unrelated runtime errors.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\QrCode\Exception;

use RuntimeException;

class QrCodeException extends RuntimeException {}
