<?php

declare(strict_types=1);

/**
 * Exception for SMTP connection failures: refused, timeout, lost.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

class ConnectionException extends RuntimeException {}
