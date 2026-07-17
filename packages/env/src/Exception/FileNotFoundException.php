<?php

declare(strict_types=1);

/**
 * FileNotFoundException
 *
 * Thrown when an environment file cannot be found at the specified path.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env\Exception;

final class FileNotFoundException extends EnvException
{
    /**
     * Create the exception for the missing env file path.
     *
     * @param string $path The path to the missing environment file.
     */
    public function __construct(string $path)
    {
        parent::__construct("Environment file not found: {$path}");
    }
}
