<?php

declare(strict_types=1);

/**
 * Thrown when downloading, verifying, extracting or executing the Bun binary fails.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Exception;

final class BinaryDownloadException extends \RuntimeException implements BunException {}
