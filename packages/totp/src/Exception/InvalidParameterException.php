<?php

declare(strict_types=1);

/**
 * Thrown when an OTP parameter is out of range — digit count, period, window,
 * counter, or secret length.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Exception;

final class InvalidParameterException extends OtpException {}
