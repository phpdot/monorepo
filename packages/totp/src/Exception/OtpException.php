<?php

declare(strict_types=1);

/**
 * Base exception for the OTP package.
 *
 * Every exception thrown by this package extends this type, so a consumer can
 * catch `OtpException` to trap any failure originating here.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Exception;

use RuntimeException;

class OtpException extends RuntimeException {}
