<?php

declare(strict_types=1);

/**
 * Thrown when a requested entry (or entry+extension) is absent from the build manifest.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Manifest;

use PHPdot\Bun\Exception\BunException;

final class ManifestEntryNotFoundException extends \RuntimeException implements BunException
{
    /**
     * @param list<string> $available
     * @param string $entry
     */
    public function __construct(string $entry, array $available)
    {
        parent::__construct(sprintf(
            'No manifest entry for "%s". Available: %s',
            $entry,
            $available === [] ? '(none)' : implode(', ', $available),
        ));
    }
}
