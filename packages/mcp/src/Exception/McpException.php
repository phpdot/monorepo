<?php

declare(strict_types=1);

/**
 * The open base of every exception the package throws, so a host can catch the whole
 * domain in one clause. Leaves are final; every SDK failure is translated into this
 * tree at the gateway fence — no Mcp\* type ever leaks past the boundary.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Exception;

use RuntimeException;

class McpException extends RuntimeException {}
