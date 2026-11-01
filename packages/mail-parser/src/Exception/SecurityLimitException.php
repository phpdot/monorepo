<?php

declare(strict_types=1);

/**
 * Exception thrown when a security limit is exceeded during email parsing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class SecurityLimitException extends ParseException {}
