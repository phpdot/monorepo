<?php

declare(strict_types=1);

/**
 * Thrown when a document fails server-side schema validation (error code 121).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

final class ValidationException extends WriteException {}
