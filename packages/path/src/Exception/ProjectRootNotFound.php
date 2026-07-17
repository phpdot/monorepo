<?php

declare(strict_types=1);

/**
 * ProjectRootNotFound
 *
 * Thrown when the project root cannot be resolved or is not a directory.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Path\Exception;

final class ProjectRootNotFound extends PathException
{
    /**
     * Build an exception for a starting path from which no project root could be found.
     *
     * @param string $path The path the upward search started from
     *
     * @return self
     */
    public static function unresolvable(string $path): self
    {
        return new self(sprintf(
            "Could not resolve the project root from Composer metadata: '%s' is not a directory.",
            $path,
        ));
    }

    /**
     * Invalid path.
     *
     * @param string $path
     *
     * @return self
     */
    public static function invalidPath(string $path): self
    {
        return new self(sprintf("Project root path is not a directory: '%s'.", $path));
    }
}
