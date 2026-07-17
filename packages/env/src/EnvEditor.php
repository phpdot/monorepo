<?php

declare(strict_types=1);

/**
 * EnvEditor
 *
 * CLI-only write tool for modifying .env files while preserving formatting.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env;

use BackedEnum;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Exception\WriteException;
use PHPdot\Env\Schema\EnvSchema;

final class EnvEditor
{
    /**
     * @var array<string, string|null> Pending changes: key => new value (null = remove).
     */
    private array $pending = [];

    /**
     * Create an editor for the env file, validating writes against the schema.
     *
     * @param string $path Path to the .env file.
     * @param EnvSchema $schema The schema to validate keys against.
     *
     * @throws WriteException If not running from CLI.
     */
    public function __construct(
        private readonly string $path,
        private readonly EnvSchema $schema,
    ) {
        if (PHP_SAPI !== 'cli') {
            throw WriteException::notCli();
        }
    }

    /**
     * Stages a key-value pair for writing.
     *
     * @param string $key The environment variable name.
     * @param string|int|float|bool|BackedEnum $value The value to set.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return void
     */
    public function set(string $key, string|int|float|bool|BackedEnum $value): void
    {
        $this->schema->getDefinition($key);
        $this->pending[$key] = $this->schema->serializeValue($key, $value);
    }

    /**
     * Stages a key for removal.
     *
     * @param string $key The environment variable name to remove.
     *
     * @return void
     */
    public function remove(string $key): void
    {
        $this->pending[$key] = null;
    }

    /**
     * Writes all pending changes to the env file.
     *
     *
     * @throws WriteException If the file cannot be written.
     *
     * @return bool True on success.
     */
    public function save(): bool
    {
        $existing = is_file($this->path) ? file_get_contents($this->path) : '';

        if ($existing === false) {
            $existing = '';
        }

        $lines = $existing !== '' ? explode("\n", $existing) : [];
        $applied = [];
        $output = [];

        foreach ($lines as $line) {
            $matchedKey = $this->extractKey($line);

            if ($matchedKey !== null && array_key_exists($matchedKey, $this->pending)) {
                $applied[$matchedKey] = true;

                if ($this->pending[$matchedKey] === null) {
                    continue;
                }

                $output[] = $matchedKey . '=' . $this->formatValue($this->pending[$matchedKey]);

                continue;
            }

            $output[] = $line;
        }

        foreach ($this->pending as $key => $value) {
            if (isset($applied[$key])) {
                continue;
            }

            if ($value === null) {
                continue;
            }

            $output[] = $key . '=' . $this->formatValue($value);
        }

        $content = implode("\n", $output);

        if ($content !== '' && !str_ends_with($content, "\n")) {
            $content .= "\n";
        }

        if (file_put_contents($this->path, $content) === false) {
            throw WriteException::writeFailed($this->path);
        }

        $this->pending = [];

        return true;
    }

    /**
     * Checks if a key exists in the file or in pending changes.
     *
     * @param string $key The environment variable name.
     *
     * @return bool True if the key exists.
     */
    public function hasKey(string $key): bool
    {
        if (array_key_exists($key, $this->pending) && $this->pending[$key] !== null) {
            return true;
        }

        if (!is_file($this->path)) {
            return false;
        }

        $content = file_get_contents($this->path);

        if ($content === false) {
            return false;
        }

        $lines = explode("\n", $content);

        foreach ($lines as $line) {
            if ($this->extractKey($line) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clears all pending changes.
     *
     * @return void
     */
    public function reset(): void
    {
        $this->pending = [];
    }

    /**
     * Extracts the key name from an env file line.
     *
     * @param string $line A single line from the env file.
     *
     * @return string|null The key name, or null if the line is not a key-value pair.
     */
    private function extractKey(string $line): ?string
    {
        $trimmed = ltrim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            return null;
        }

        $withoutExport = preg_replace('/^export\s+/', '', $trimmed);

        if ($withoutExport === null) {
            return null;
        }

        $equalsPos = strpos($withoutExport, '=');

        if ($equalsPos === false) {
            return null;
        }

        return trim(substr($withoutExport, 0, $equalsPos));
    }

    /**
     * Formats a value for writing to an env file, quoting if necessary.
     *
     * @param string $value The serialized value to format.
     *
     * @return string The formatted value, quoted if it contains special characters.
     */
    private function formatValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s#"\'\\\\$]/', $value) === 1) {
            $escaped = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);

            return '"' . $escaped . '"';
        }

        return $value;
    }
}
