<?php

declare(strict_types=1);

/**
 * Pool was shut down via close(). No more connections can be borrowed.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool\Exception;

final class PoolClosedException extends PoolException {}
