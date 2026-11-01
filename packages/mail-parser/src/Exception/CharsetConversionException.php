<?php

declare(strict_types=1);

/**
 * Exception thrown when character set conversion fails during email parsing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Exception;

final class CharsetConversionException extends ParseException {}
