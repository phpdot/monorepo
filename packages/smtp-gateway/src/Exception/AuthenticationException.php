<?php

declare(strict_types=1);

/**
 * Exception for SMTP authentication failures.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

class AuthenticationException extends RuntimeException {}
