<?php

declare(strict_types=1);

/**
 * Thrown when a secret is empty or contains invalid Base32 characters.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Totp\Exception;

final class InvalidSecretException extends OtpException {}
