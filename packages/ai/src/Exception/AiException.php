<?php

declare(strict_types=1);

/**
 * The open base of every exception the package throws, so a host can catch the
 * whole domain in one clause. The layer keeps a single leaf beneath it — the
 * one-failure-vocabulary doctrine survives; the base is only the catch seam.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Ai\Exception;

use RuntimeException;

class AiException extends RuntimeException {}
