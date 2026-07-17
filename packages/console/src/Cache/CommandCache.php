<?php

declare(strict_types=1);

/**
 * Filesystem cache of the discovered command map.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Console\Cache;

final class CommandCache
{
    /**
     * Create the command cache at the given file path.
     *
     * @param string $path Path to the cache file
     */
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * Check if the cache file exists.
     *
     * @return bool
     */
    public function has(): bool
    {
        return file_exists($this->path);
    }

    /**
     * Read the cached command map.
     *
     * @return array<string, class-string>|null Command name to class map, or null if cache missing
     */
    public function read(): ?array
    {
        if (!file_exists($this->path)) {
            return null;
        }

        /**
         * @var array<string, class-string> $data
         */
        $data = require $this->path;

        return $data;
    }

    /**
     * Write a command map to the cache file.
     *
     * @param array<string, class-string> $commandMap Command name to class map
     *
     * @return void
     */
    public function write(array $commandMap): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($commandMap, true) . ";\n";

        file_put_contents($this->path, $content);
    }

    /**
     * Delete the cache file if it exists.
     *
     * @return void
     */
    public function clear(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }
}
