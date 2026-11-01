<?php

declare(strict_types=1);

/**
 * Exception thrown when an email header violates RFC 5322 formatting rules.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class InvalidHeaderException extends ParseException {}
