<?php

declare(strict_types=1);

/**
 * Exception for invalid SMTP state transitions.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Exception;

class StateException extends RuntimeException {}
