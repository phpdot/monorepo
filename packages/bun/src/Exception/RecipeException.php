<?php

declare(strict_types=1);

/**
 * Thrown when a recipe file is malformed or declares nothing to build.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Bun\Exception;

use RuntimeException;

final class RecipeException extends RuntimeException implements BunException
{
    /**
     * A recipe that declares no entrypoints.
     *
     * @return self
     */
    public static function noEntrypoints(): self
    {
        return new self('The recipe declares no entrypoints — call $recipe->entries(...) in resources/build.php');
    }

    /**
     * A recipe file that does not return a callable.
     *
     * @param string $path The recipe file that was loaded
     *
     * @return self
     */
    public static function notCallable(string $path): self
    {
        return new self("{$path} must return a callable(Recipe): void");
    }

    /**
     * A missing recipe file, when one was required.
     *
     * @param string $path The expected recipe path
     *
     * @return self
     */
    public static function missing(string $path): self
    {
        return new self("No recipe at {$path}");
    }
}
