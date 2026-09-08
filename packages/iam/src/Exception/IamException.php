<?php

declare(strict_types=1);

/**
 * The open base of every exception the package throws, so a host can catch
 * the whole domain in one clause. Leaves are final and carry named static
 * constructors where a failure carries context.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

use RuntimeException;

class IamException extends RuntimeException {}
