<?php

declare(strict_types=1);

/**
 * Exception thrown when the MIME structure of an email is malformed or invalid.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class InvalidMimeStructureException extends ParseException {}
