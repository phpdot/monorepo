<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit;

use PHPdot\Console\Application;
use PHPdot\Console\Cache\CommandCache;
use PHPdot\Console\ClearCommand;
use PHPdot\Console\Tests\Fixtures\GreetCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdot_console_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_file($this->tempDir . '/cache.php')) {
            unlink($this->tempDir . '/cache.php');
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function clearsAnExistingCacheFile(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');
        $cache->write(['greet' => GreetCommand::class]);

        $tester = new CommandTester(new ClearCommand($cache));

        self::assertSame(0, $tester->execute([]));
        self::assertFalse($cache->has());
        self::assertStringContainsString('cleared', $tester->getDisplay());
    }

    #[Test]
    public function reportsWhenTheCacheIsAlreadyEmpty(): void
    {
        $tester = new CommandTester(new ClearCommand(new CommandCache($this->tempDir . '/cache.php')));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('already empty', $tester->getDisplay());
    }

    #[Test]
    public function reportsWhenNoCacheIsConfigured(): void
    {
        $tester = new CommandTester(new ClearCommand(null));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('disabled', $tester->getDisplay());
    }

    #[Test]
    public function theApplicationAlwaysRegistersIt(): void
    {
        $application = new Application();

        self::assertTrue($application->getSymfonyApplication()->has('console:clear'));
    }
}
