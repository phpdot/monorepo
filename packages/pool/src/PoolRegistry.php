<?php

declare(strict_types=1);

/**
 * Named-pool facade: config blocks to pools, borrowed connections to coroutine
 * scopes.
 *
 * A pool name maps to a configuration block — `database` reads the
 * `config/database.php` section, `redis.cache` reads the `pools.cache` block of
 * `config/redis.php`. The block's `pool` key sizes the pool; everything else in
 * the block hydrates the connector's own configuration DTO, discovered from the
 * connector's first constructor parameter. Pools are built lazily on first use,
 * inside the worker that first resolves them, and self-initialise on first
 * borrow.
 *
 * Outside a coroutine — CLI, boot, migrations — `connection()` hands a
 * dedicated, unpooled connection whose `release()` closes it, so the same call
 * shape works everywhere.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Pool;

use PHPdot\Config\Configuration;
use PHPdot\Contracts\Pool\ConnectorInterface;
use PHPdot\Pool\Exception\PoolException;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionNamedType;
use SplObjectStorage;
use Swoole\Coroutine;
use Throwable;

final class PoolRegistry
{
    /**
     * @var array<string, Pool>
     */
    private array $pools = [];

    /**
     * @var array<string, ConnectorInterface>
     */
    private array $connectors = [];

    /**
     * Connectors behind the dedicated connections handed out outside a
     * coroutine, keyed by the connection instance.
     *
     * @var SplObjectStorage<object, ConnectorInterface>
     */
    private readonly SplObjectStorage $dedicated;

    /**
     * Wire the registry to the application's configuration and container.
     *
     * @param Configuration $config Reads each pool's block from its own section
     * @param ContainerInterface $container Resolves the connector's secondary collaborators
     */
    public function __construct(
        private readonly Configuration $config,
        private readonly ContainerInterface $container,
    ) {
        $this->dedicated = new SplObjectStorage();
    }

    /**
     * A connection from the named pool — borrowed inside a coroutine, dedicated
     * outside one.
     *
     * @param string $name The pool name: a section, or "section.pool" for a
     *                     multi-pool client's `pools` sub-block
     * @param class-string<ConnectorInterface> $connector The client package's connector
     *
     * @throws PoolException When the named block is missing or malformed
     *
     * @return object The borrowed or dedicated connection
     */
    public function connection(string $name, string $connector): object
    {
        if (Coroutine::getCid() < 0) {
            $instance = $this->connector($name, $connector);
            $connection = $instance->connect();
            $this->dedicated[$connection] = $instance;

            return $connection;
        }

        return $this->pool($name, $connector)->borrow();
    }

    /**
     * Return a connection handed out by `connection()`: pooled inside a
     * coroutine, closed when it was dedicated.
     *
     * @param string $name The pool name the connection came from
     * @param class-string<ConnectorInterface> $connector The client package's connector
     * @param object $connection The connection to return
     *
     * @return void
     */
    public function release(string $name, string $connector, object $connection): void
    {
        if (isset($this->dedicated[$connection])) {
            $instance = $this->dedicated[$connection];
            unset($this->dedicated[$connection]);

            try {
                $instance->close($connection);
            } catch (Throwable) {
            }

            return;
        }

        $this->pool($name, $connector)->release($connection);
    }

    /**
     * The named pool, built lazily on first use and reused thereafter.
     *
     * @param string $name The pool name
     * @param class-string<ConnectorInterface> $connector The client package's connector
     *
     * @return Pool
     */
    public function pool(string $name, string $connector): Pool
    {
        if (isset($this->pools[$name])) {
            return $this->pools[$name];
        }

        $block = $this->block($name);
        $sizing = $block['pool'] ?? [];
        $poolSettings = is_array($sizing) ? $sizing : [];

        return $this->pools[$name] = new Pool(
            $this->connector($name, $connector),
            $this->poolConfig($this->stringKeys($poolSettings)),
        );
    }

    /**
     * Stop every built pool's maintenance timers, keeping all of them
     * borrowable — the worker-exit drain call. A pool is never closed: closing
     * kicks coroutines parked in `borrow()` and surfaces as request failures.
     *
     * @return void
     */
    public function suspendAll(): void
    {
        foreach ($this->pools as $pool) {
            $pool->suspendTimers();
        }
    }

    /**
     * The worker-exit drain: stop every built pool's timers AND its
     * validation — a parked liveness ping on release holds the worker drain
     * open past every deadline, and closing answers instantly. Pools stay
     * borrowable: a borrower still finishing work gets a fresh connection.
     *
     * @return void
     */
    public function drainAll(): void
    {
        foreach ($this->pools as $pool) {
            $pool->drain();
        }
    }

    /**
     * The pool name's configuration block: the section itself for a plain
     * name, the `pools.<name>` sub-block for a dotted one.
     *
     * @param string $name The pool name
     *
     * @throws PoolException When the section or sub-block does not exist
     *
     * @return array<string, mixed> The block, with the `pool` sizing key still in place
     */
    private function block(string $name): array
    {
        $parts = explode('.', $name, 2);
        $data = $this->config->section($parts[0]);

        if (count($parts) === 1) {
            if ($data === []) {
                throw new PoolException("Pool '{$name}' has no config/{$parts[0]}.php section");
            }

            return $data;
        }

        $pools = $data['pools'] ?? null;

        if (!is_array($pools) || !is_array($pools[$parts[1]] ?? null)) {
            throw new PoolException("Pool '{$name}' has no pools.{$parts[1]} block in config/{$parts[0]}.php");
        }

        /**
         * @var array<string, mixed> $sub
         */
        $sub = $pools[$parts[1]];

        return $sub;
    }

    /**
     * Build the client's connector: its first constructor parameter hydrates
     * from the block's non-pool keys, every later parameter resolves from the
     * container or takes its declared default.
     *
     * @param string $name The pool name, for the block lookup
     * @param class-string<ConnectorInterface> $connector The connector class
     *
     * @throws PoolException When the connector cannot be built from the block
     *
     * @return ConnectorInterface
     */
    private function connector(string $name, string $connector): ConnectorInterface
    {
        if (isset($this->connectors[$name])) {
            return $this->connectors[$name];
        }

        $block = $this->block($name);
        unset($block['pool']);

        $reflection = new ReflectionClass($connector);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return $reflection->newInstance();
        }

        $args = [];

        foreach ($constructor->getParameters() as $index => $parameter) {
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;

            if ($index === 0 && $typeName !== null && class_exists($typeName)) {
                $args[] = $this->config->dtoFromArray($block, $typeName, "pool '{$name}'");

                continue;
            }

            if ($typeName !== null && (class_exists($typeName) || interface_exists($typeName)) && $this->container->has($typeName)) {
                $args[] = $this->container->get($typeName);

                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();

                continue;
            }

            throw new PoolException(
                "Connector {$connector} parameter \${$parameter->getName()} cannot be resolved for pool '{$name}'",
            );
        }

        return $this->connectors[$name] = $reflection->newInstanceArgs($args);
    }

    /**
     * A `pool` sizing block onto a PoolConfig, by full or shorthand keys.
     *
     * @param array<string, mixed> $pool The `pool` key's value
     *
     * @return PoolConfig
     */
    private function poolConfig(array $pool): PoolConfig
    {
        $min = $pool['min'] ?? $pool['minConnections'] ?? 2;
        $max = $pool['max'] ?? $pool['maxConnections'] ?? 10;

        return new PoolConfig(
            minConnections: is_int($min) ? $min : 2,
            maxConnections: is_int($max) ? $max : 10,
            borrowTimeout: $this->float($pool, 'borrowTimeout', 3.0),
            maxIdleTime: $this->float($pool, 'maxIdleTime', 300.0),
            idleCheckInterval: $this->float($pool, 'idleCheckInterval', 30.0),
            heartbeatInterval: $this->float($pool, 'heartbeatInterval', 0.0),
            validateOnBorrowAfterIdle: $this->float($pool, 'validateOnBorrowAfterIdle', 5.0),
            validateOnReturn: is_bool($pool['validateOnReturn'] ?? null) ? $pool['validateOnReturn'] : true,
        );
    }

    /**
     * A sizing key as a float, or the PoolConfig default when absent or not
     * numeric.
     *
     * @param array<string, mixed> $pool The sizing block
     * @param string $key The key to read
     * @param float $default The default when absent
     *
     * @return float
     */
    private function float(array $pool, string $key, float $default): float
    {
        $value = $pool[$key] ?? null;

        return is_int($value) || is_float($value) ? (float) $value : $default;
    }

    /**
     * Narrow a sizing block's values to the string-keyed map poolConfig reads.
     *
     * @param array<mixed> $pool The sizing block as read from config
     *
     * @return array<string, mixed>
     */
    private function stringKeys(array $pool): array
    {
        $keys = [];

        foreach ($pool as $key => $value) {
            if (is_string($key)) {
                $keys[$key] = $value;
            }
        }

        return $keys;
    }
}
