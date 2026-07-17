<?php

declare(strict_types=1);

/**
 * Env
 *
 * Main read-only facade for accessing typed, schema-validated environment variables.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Env;

use PHPdot\Env\Exception\FileNotFoundException;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Exception\ValidationException;
use PHPdot\Env\Exception\WriteException;
use PHPdot\Env\Parser\Parser;
use PHPdot\Env\Schema\EnvSchema;

final class Env
{
    private static ?self $instance = null;

    /**
     * @var array<string, string>
     */
    private readonly array $rawValues;

    /**
     * @var array<string, mixed>
     */
    private readonly array $typedValues;

    private readonly EnvSchema $schema;

    /**
     * @var list<string>
     */
    private readonly array $loadedFiles;

    /**
     * Validate the raw values against the schema and build the typed value set.
     *
     * @param EnvSchema $schema The schema to validate against.
     * @param array<string, string> $rawValues Raw key-value pairs from env files.
     * @param list<string> $loadedFiles Paths of loaded env files.
     *
     * @throws ValidationException If required keys are missing or values fail validation.
     * @throws SchemaException If a key is not defined in the schema.
     */
    private function __construct(EnvSchema $schema, array $rawValues, array $loadedFiles)
    {
        $this->schema = $schema;
        $this->rawValues = $rawValues;
        $this->loadedFiles = $loadedFiles;

        $missing = [];

        foreach ($schema->getKeys() as $key) {
            if ($schema->isRequired($key) && !isset($rawValues[$key]) && $schema->getDefault($key) === null) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw ValidationException::missingRequired($missing);
        }

        $typed = [];

        foreach ($schema->getKeys() as $key) {
            $raw = $rawValues[$key] ?? null;
            $value = $schema->castValue($key, $raw);
            $schema->validateConstraints($key, $value);
            $typed[$key] = $value;
        }

        $this->typedValues = $typed;
    }

    /**
     * Creates an Env instance from a schema and one or more env file paths.
     *
     * @param string|array<string, array<string, mixed>> $schema Schema file path or inline array.
     * @param string|list<string> $paths One or more env file paths.
     *
     * @throws FileNotFoundException If an env file does not exist.
     * @throws ValidationException If required keys are missing or values fail validation.
     * @throws SchemaException If the schema is invalid.
     *
     * @return self
     */
    public static function create(string|array $schema, string|array $paths = []): self
    {
        $envSchema = new EnvSchema($schema);
        $normalizedPaths = is_string($paths) ? [$paths] : $paths;
        $parser = Parser::create();
        $merged = [];

        foreach ($normalizedPaths as $path) {
            if (!is_file($path)) {
                throw new FileNotFoundException($path);
            }

            $content = file_get_contents($path);

            if ($content === false) {
                throw new FileNotFoundException($path);
            }

            $parsed = $parser->parse($content, $path, $merged);
            $merged = array_merge($merged, $parsed);
        }

        return new self($envSchema, $merged, $normalizedPaths);
    }

    /**
     * Creates an Env instance, silently skipping any missing files.
     *
     * @param string|array<string, array<string, mixed>> $schema Schema file path or inline array.
     * @param string|list<string> $paths One or more env file paths.
     *
     * @throws ValidationException If required keys are missing or values fail validation.
     * @throws SchemaException If the schema is invalid.
     *
     * @return Env
     */
    public static function safeCreate(string|array $schema, string|array $paths = []): self
    {
        $envSchema = new EnvSchema($schema);
        $normalizedPaths = is_string($paths) ? [$paths] : $paths;
        $parser = Parser::create();
        $merged = [];
        $loaded = [];

        foreach ($normalizedPaths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                continue;
            }

            $parsed = $parser->parse($content, $path, $merged);
            $merged = array_merge($merged, $parsed);
            $loaded[] = $path;
        }

        return new self($envSchema, $merged, $loaded);
    }

    /**
     * Creates an Env instance for testing without file I/O.
     *
     * @param string|array<string, array<string, mixed>> $schema Schema file path or inline array.
     * @param array<string, string> $values Raw key-value pairs.
     *
     * @throws ValidationException If required keys are missing or values fail validation.
     * @throws SchemaException If the schema is invalid.
     *
     * @return Env
     */
    public static function createForTesting(string|array $schema, array $values = []): self
    {
        $envSchema = new EnvSchema($schema);

        return new self($envSchema, $values, []);
    }

    /**
     * Parses raw env content into key-value pairs without schema validation.
     *
     * @param string $content Raw .env file content.
     *
     * @return array<string, string> Parsed key-value pairs.
     */
    public static function parseString(string $content): array
    {
        return Parser::create()->parse($content);
    }

    /**
     * Creates an Env instance from a compiled cache file.
     *
     * @param string|array<string, array<string, mixed>> $schema Schema file path or inline array.
     * @param string $cachePath Path to the compiled PHP cache file.
     *
     * @throws FileNotFoundException If the cache file does not exist.
     * @throws ValidationException If required keys are missing or values fail validation.
     * @throws SchemaException If the schema is invalid.
     *
     * @return Env
     */
    public static function createFromCache(string|array $schema, string $cachePath): self
    {
        if (!is_file($cachePath)) {
            throw new FileNotFoundException($cachePath);
        }

        /**
         * @var array{raw: array<string, string>, files: list<string>, compiled_at: int} $data
         */
        $data = require $cachePath;
        $envSchema = new EnvSchema($schema);

        return new self($envSchema, $data['raw'], $data['files']);
    }

    /**
     * Returns the typed value for the given key.
     *
     * @param string $key The environment variable name.
     *
     * @throws SchemaException If the key is not defined in the schema.
     *
     * @return mixed The typed value (includes defaults for missing keys).
     */
    public function get(string $key): mixed
    {
        if (!$this->schema->has($key)) {
            throw SchemaException::unknownKey($key);
        }

        return $this->typedValues[$key];
    }

    /**
     * Checks if a key was explicitly set in an env file (not just defaulted).
     *
     * @param string $key The environment variable name.
     *
     * @return bool True if the key exists in the raw values.
     */
    public function has(string $key): bool
    {
        return isset($this->rawValues[$key]);
    }

    /**
     * Returns all typed values.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->typedValues;
    }

    /**
     * Returns all typed values with sensitive keys masked.
     *
     * @return array<string, mixed>
     */
    public function allMasked(): array
    {
        $masked = [];

        foreach ($this->typedValues as $key => $value) {
            $masked[$key] = $this->schema->isSensitive($key) ? '***' : $value;
        }

        return $masked;
    }

    /**
     * Returns the raw string value for the given key.
     *
     * @param string $key The environment variable name.
     *
     * @return string|null The raw value, or null if not set.
     */
    public function getRaw(string $key): ?string
    {
        return $this->rawValues[$key] ?? null;
    }

    /**
     * Returns the schema instance.
     *
     * @return EnvSchema
     */
    public function getSchema(): EnvSchema
    {
        return $this->schema;
    }

    /**
     * Compiles the raw values to a PHP cache file for fast loading.
     *
     * @param string $outputPath Path to write the compiled PHP file.
     *
     * @throws WriteException If the file cannot be written.
     *
     * @return void
     */
    public function compile(string $outputPath): void
    {
        $data = [
            'raw' => $this->rawValues,
            'files' => $this->loadedFiles,
            'compiled_at' => time(),
        ];

        $content = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($data, true) . ";\n";

        if (file_put_contents($outputPath, $content) === false) {
            throw WriteException::writeFailed($outputPath);
        }
    }

    /**
     * Returns the list of loaded file paths.
     *
     * @return list<string>
     */
    public function getLoadedFiles(): array
    {
        return $this->loadedFiles;
    }

    /**
     * Initialize the global Env instance backing the env() helper.
     *
     * Called once by the kernel during boot. Uses safeCreate so missing
     * .env files are silently skipped. Schema validates and types all values.
     *
     * @param string|array<string, array<string, mixed>> $schema Schema file path or inline array
     * @param string|list<string> $paths One or more .env file paths
     *
     * @return void
     */
    public static function init(string|array $schema, string|array $paths): void
    {
        self::$instance = self::safeCreate($schema, $paths);
    }

    /**
     * Retrieve a typed environment value from the global instance.
     *
     * Returns the schema-validated, type-cast value. If the key is not
     * in the schema or no instance is initialized, returns $default.
     *
     * @param mixed $default
     * @param string $key
     *
     * @return mixed
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        if (self::$instance === null) {
            return $default;
        }

        if (!self::$instance->schema->has($key)) {
            return $default;
        }

        return self::$instance->typedValues[$key] ?? $default;
    }

    /**
     * Get the global instance, or null if not initialized.
     *
     * @return ?Env
     */
    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    /**
     * Reset the global instance. Used in testing.
     *
     * @return void
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }
}
