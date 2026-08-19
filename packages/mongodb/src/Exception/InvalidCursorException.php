<?php

declare(strict_types=1);

/**
 * Thrown when an opaque pagination cursor cannot be decoded.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

final class InvalidCursorException extends MongoException {}
