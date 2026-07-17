<?php

declare(strict_types=1);

/**
 * Thrown when MongoDB authentication fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\MongoDB\Exception;

final class AuthenticationException extends MongoException {}
