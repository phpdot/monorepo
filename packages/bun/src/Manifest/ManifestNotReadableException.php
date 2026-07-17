<?php

declare(strict_types=1);

/**
 * Thrown when the Bun metafile cannot be read (missing or unreadable path).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Manifest;

use PHPdot\Bun\Exception\BunException;

final class ManifestNotReadableException extends \RuntimeException implements BunException
{
    /**
     * Build the exception for an unreadable build manifest at the given path.
     *
     * @param string $path
     */
    public function __construct(string $path)
    {
        parent::__construct(sprintf('Build manifest not readable: %s', $path));
    }
}
