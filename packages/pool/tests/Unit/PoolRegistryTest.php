<?php

declare(strict_types=1);

namespace PHPdot\Pool\Tests\Unit;

use PHPdot\Config\Configuration;
use PHPdot\Container\Scope;
use PHPdot\Pool\Exception\PoolException;

use function PHPdot\Pool\pooled;

use PHPdot\Pool\PoolRegistry;
use PHPdot\Pool\Tests\Fixtures\FakeConnection;
use PHPdot\Pool\Tests\Fixtures\MapContainer;
use PHPdot\Pool\Tests\Fixtures\RegistryConnector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class PoolRegistryTest extends TestCase
{
    private string $configDir;

    protected function setUp(): void
    {
        $this->configDir = sys_get_temp_dir() . '/phpdot-pool-registry-' . bin2hex(random_bytes(4));
        mkdir($this->configDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->configDir . '/*.php') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->configDir);
    }

    #[Test]
    public function aDedicatedConnectionOutsideACoroutineIsClosedOnRelease(): void
    {
        $this->write('database.php', ['host' => 'db.example', 'pool' => ['max' => 4]]);
        $registry = $this->registry();

        $connection = $registry->connection('database', RegistryConnector::class);

        self::assertInstanceOf(FakeConnection::class, $connection);
        self::assertFalse($connection->closed, 'a dedicated connection is handed out live');

        $registry->release('database', RegistryConnector::class, $connection);

        self::assertTrue($connection->closed, 'a dedicated connection closes on release — never pooled');
    }

    #[Test]
    public function theConnectorHydratesItsConfigFromTheBlockAndSizesThePool(): void
    {
        $this->write('database.php', ['host' => 'db.example', 'port' => 4000, 'pool' => ['min' => 1, 'max' => 7]]);
        $registry = $this->registry();

        $registry->connection('database', RegistryConnector::class);

        $pool = $registry->pool('database', RegistryConnector::class);
        $config = new ReflectionProperty($pool, 'config');

        self::assertSame(7, $config->getValue($pool)->maxConnections, "the block's pool key sizes the pool");

        $connector = new ReflectionProperty($pool, 'connector');
        $built = $connector->getValue($pool)->built;

        self::assertCount(1, $built);
        self::assertSame('db.example', $built[0]->host, "the block's remaining keys hydrate the connector's config");
        self::assertSame(4000, $built[0]->port);
    }

    #[Test]
    public function aDottedNameReadsItsPoolsSubBlock(): void
    {
        $this->write('redis.php', [
            'pools' => [
                'cache' => ['host' => 'cache.example', 'pool' => ['max' => 20]],
                'session' => ['host' => 'session.example'],
            ],
        ]);
        $registry = $this->registry();

        $connection = $registry->connection('redis.cache', RegistryConnector::class);
        $pool = $registry->pool('redis.cache', RegistryConnector::class);

        $connector = new ReflectionProperty($pool, 'connector');

        self::assertInstanceOf(FakeConnection::class, $connection);
        self::assertSame('cache.example', $connector->getValue($pool)->built[0]->host, 'the sub-block hydrates, not the section');
    }

    #[Test]
    public function aMissingSectionOrSubBlockThrows(): void
    {
        $registry = $this->registry();

        $this->expectException(PoolException::class);
        $this->expectExceptionMessage('database');

        $registry->connection('database', RegistryConnector::class);
    }

    #[Test]
    public function pooledBuildsAScopedDefinitionOverBorrowAndRelease(): void
    {
        $this->write('database.php', ['host' => 'db.example']);
        $registry = $this->registry();
        $container = new MapContainer([PoolRegistry::class => $registry]);

        $definition = pooled('database', RegistryConnector::class);

        self::assertSame(Scope::SCOPED, $definition->scope, 'per-coroutine resolution, released at context end');

        $connection = ($definition->factory)($container);
        self::assertInstanceOf(FakeConnection::class, $connection);
        self::assertFalse($connection->closed);

        ($definition->onDestroy)($connection, $container);
        self::assertTrue($connection->closed, 'the destroy hook releases through the registry');
    }

    private function write(string $file, array $data): void
    {
        file_put_contents(
            $this->configDir . '/' . $file,
            "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($data, true) . ";\n",
        );
    }

    private function registry(): PoolRegistry
    {
        return new PoolRegistry(new Configuration($this->configDir, 'production'), new MapContainer());
    }
}
