<?php

declare(strict_types=1);

/**
 * File-backed cache for scanned attribute maps.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Attribute\Cache;

use PHPdot\Attribute\Exception\AttributeException;
use PHPdot\Attribute\Result\AttributeMap;

final class FileCache
{
    /**
     * Create a cache bound to the given file path.
     *
     * @param string $path
     */
    public function __construct(
        private readonly string $path,
    ) {}

    /**
     * Remove the cached attribute map from disk.
     *
     * @return void
     */
    public function clear(): void
    {
        if (file_exists($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * Whether a cached attribute map exists on disk.
     *
     * @return bool
     */
    public function has(): bool
    {
        return file_exists($this->path);
    }

    /**
     * The cached attribute map, or null when no cache file exists.
     *
     * @return ?AttributeMap
     */
    public function read(): null|AttributeMap
    {
        if (!file_exists($this->path)) {
            return null;
        }

        /**
         * @var array{
         *     classes: array<string, array{
         *         class: string,
         *         structureType: string,
         *         implements: list<string>,
         *         extends: ?string,
         *         results: list<array{
         *             attribute: string,
         *             arguments: list<mixed>,
         *             class: string,
         *             target: string,
         *             method: ?string,
         *             property: ?string,
         *             parameter: ?string,
         *             constant: ?string
         *         }>
         *     }>,
         *     generatedAt: int,
         *     directories: list<string>,
         *     filter: list<string>,
         *     visibilityFilter?: int
         * } $data
         */
        $data = require $this->path;

        return AttributeMap::fromCache($data);
    }

    /**
     * Persist the attribute map to the cache file.
     *
     * Written to a temporary path and renamed into place: the cache file is
     * require()d back, so a partial write must never be observable — a
     * truncated file would be a parse error at every subsequent boot.
     * Failures throw instead of silently dropping the map.
     *
     * @param AttributeMap $map
     *
     * @throws AttributeException If the directory or file cannot be written.
     *
     * @return void
     */
    public function write(AttributeMap $map): void
    {
        $directory = dirname($this->path);

        if (!is_dir($directory) && !@mkdir($directory, 0o755, true) && !is_dir($directory)) {
            throw new AttributeException(
                \sprintf('Unable to create attribute cache directory: %s', $directory),
            );
        }

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($map->toCache(), true) . ";\n";
        $tempPath = $this->path . '.' . uniqid('', true) . '.tmp';

        if (@file_put_contents($tempPath, $content) === false) {
            throw new AttributeException(
                \sprintf('Unable to write attribute cache file: %s', $this->path),
            );
        }

        if (!@rename($tempPath, $this->path)) {
            @unlink($tempPath);
            throw new AttributeException(
                \sprintf('Unable to write attribute cache file: %s', $this->path),
            );
        }
    }
}
