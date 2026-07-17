<?php

declare(strict_types=1);

/**
 * All maxConnections are borrowed and no one released within borrowTimeout.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool\Exception;

final class BorrowTimeoutException extends PoolException {}
