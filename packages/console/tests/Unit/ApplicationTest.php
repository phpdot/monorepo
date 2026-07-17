<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use PHPdot\Console\Application;
use PHPdot\Console\Cache\CommandCache;
use PHPdot\Console\ConsoleConfig;
use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPdot\Console\Tests\Fixtures\MathAddCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class ApplicationTest extends TestCase
{
    private string $tempDir;

    private ContainerInterface $stubContainer;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdot_app_test_' . uniqid();
        $this->stubContainer = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                if ($id === \PHPdot\Console\Tests\Fixtures\DependencyCommand::class) {
                    return new \PHPdot\Console\Tests\Fixtures\DependencyCommand('test');
                }

                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->tempDir);
    }

    #[Test]
    public function constructorCreatesApplicationWithNameAndVersion(): void
    {
        $app = new Application(new ConsoleConfig(name: 'TestApp', version: '3.0.0'));

        $symfony = $app->getSymfonyApplication();

        self::assertSame('TestApp', $symfony->getName());
        self::assertSame('3.0.0', $symfony->getVersion());
    }

    #[Test]
    public function addRegistersCommandInstance(): void
    {
        $app = new Application();

        $app->add(new GreetCommand());

        $symfony = $app->getSymfonyApplication();
        self::assertTrue($symfony->has('greet'));
    }

    #[Test]
    public function addReturnsSelfForChaining(): void
    {
        $app = new Application();

        $result = $app->add(new GreetCommand());

        self::assertSame($app, $result);
    }

    #[Test]
    public function runExecutesCommand(): void
    {
        $app = new Application();
        $app->add(new GreetCommand());

        $app->getSymfonyApplication()->setAutoExit(false);

        $input = new ArrayInput(['command' => 'greet', 'name' => 'PHPdot']);
        $output = new BufferedOutput();

        $exitCode = $app->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Hello, PHPdot!', $output->fetch());
    }

    #[Test]
    public function callExecutesCommandProgrammatically(): void
    {
        $app = new Application();
        $app->add(new MathAddCommand());

        $exitCode = $app->call('math:add', ['a' => '5', 'b' => '3']);

        self::assertSame(0, $exitCode);
    }

    #[Test]
    public function registerRegistersCommandClasses(): void
    {
        $app = new Application();

        $app->register([GreetCommand::class, MathAddCommand::class]);

        $symfony = $app->getSymfonyApplication();
        self::assertTrue($symfony->has('greet'));
        self::assertTrue($symfony->has('math:add'));
    }

    #[Test]
    public function discoverFindsCommandsInFixturesDirectory(): void
    {
        $app = new Application(container: $this->stubContainer);
        $fixturesDir = dirname(__DIR__) . '/Fixtures';

        $app->discover([$fixturesDir]);

        $symfony = $app->getSymfonyApplication();
        self::assertTrue($symfony->has('greet'));
        self::assertTrue($symfony->has('math:add'));
        self::assertTrue($symfony->has('broken'));
    }

    #[Test]
    public function discoverWithCacheWritesCacheFile(): void
    {
        $cachePath = $this->tempDir . '/commands.cache.php';
        $cache = new CommandCache($cachePath);
        $fixturesDir = dirname(__DIR__) . '/Fixtures';

        $app = new Application(container: $this->stubContainer, cache: $cache);
        $app->discover([$fixturesDir]);

        self::assertFileExists($cachePath);
    }

    #[Test]
    public function discoverWithCacheReadsFromCacheOnSecondCall(): void
    {
        $cachePath = $this->tempDir . '/commands.cache.php';
        $cache = new CommandCache($cachePath);
        $fixturesDir = dirname(__DIR__) . '/Fixtures';

        $app1 = new Application(container: $this->stubContainer, cache: $cache);
        $app1->discover([$fixturesDir]);

        self::assertFileExists($cachePath);

        // Second application reads from cache
        $app2 = new Application(container: $this->stubContainer, cache: $cache);
        $app2->discover([$fixturesDir]);

        $symfony = $app2->getSymfonyApplication();
        self::assertTrue($symfony->has('greet'));
    }

    #[Test]
    public function discoverWithForceRescanIgnoresCache(): void
    {
        $cachePath = $this->tempDir . '/commands.cache.php';
        $cache = new CommandCache($cachePath);
        $fixturesDir = dirname(__DIR__) . '/Fixtures';

        // Write a stale cache with only one command
        $cache->write(['greet' => GreetCommand::class]);

        $app = new Application(container: $this->stubContainer, cache: $cache);
        $app->discover([$fixturesDir], forceRescan: true);

        $symfony = $app->getSymfonyApplication();
        // After rescan, more commands should be available
        self::assertTrue($symfony->has('greet'));
        self::assertTrue($symfony->has('math:add'));
    }

    #[Test]
    public function getSymfonyApplicationReturnsSymfonyApplication(): void
    {
        $app = new Application();

        self::assertInstanceOf(SymfonyApplication::class, $app->getSymfonyApplication());
    }

    #[Test]
    public function callWithOutputParameterCapturesOutput(): void
    {
        $app = new Application();
        $app->add(new GreetCommand());

        $output = new BufferedOutput();
        $exitCode = $app->call('greet', ['name' => 'Test'], $output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Hello, Test!', $output->fetch());
    }

    #[Test]
    public function wireCommandsDoesNotReInstantiateAlreadyWiredCommands(): void
    {
        $app = new Application();

        $app->register([GreetCommand::class]);
        $app->register([MathAddCommand::class]);

        $symfony = $app->getSymfonyApplication();
        self::assertTrue($symfony->has('greet'));
        self::assertTrue($symfony->has('math:add'));
    }

    #[Test]
    public function containerIntegrationResolvesCommandsViaContainer(): void
    {
        $container = new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                return new $id();
            }

            public function has(string $id): bool
            {
                return class_exists($id);
            }
        };

        $app = new Application(container: $container);
        $fixturesDir = dirname(__DIR__) . '/Fixtures';

        $app->discover([$fixturesDir]);

        $app->getSymfonyApplication()->setAutoExit(false);

        $input = new ArrayInput(['command' => 'greet']);
        $output = new BufferedOutput();

        $exitCode = $app->run($input, $output);

        self::assertSame(0, $exitCode);
        self::assertStringContainsString('Hello, World!', $output->fetch());
    }

    private function cleanUp(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
