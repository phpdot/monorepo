<?php

declare(strict_types=1);

/**
 * PathNotMapped
 *
 * Thrown when resolving a path name that is not present in the path map.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path\Exception;

final class PathNotMapped extends PathException
{
    /**
     * Build an exception for a path name that was never mapped, listing the known names.
     *
     * @param string $name The unmapped path name that was requested
     * @param list<string> $known All currently mapped path names
     *
     * @return self
     */
    public static function name(string $name, array $known): self
    {
        sort($known);

        return new self(sprintf(
            "No path mapped for '%s'. Known paths: %s.",
            $name,
            implode(', ', $known),
        ));
    }
}
