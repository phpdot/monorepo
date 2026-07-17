<?php

declare(strict_types=1);

namespace PHPdot\Console\Tests\Unit\Cache;

use PHPdot\Console\Cache\CommandCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandCacheTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpdot_console_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->tempDir);
    }

    #[Test]
    public function hasReturnsFalseWhenFileDoesNotExist(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        self::assertFalse($cache->has());
    }

    #[Test]
    public function hasReturnsTrueAfterWrite(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        $cache->write(['greet' => 'App\\GreetCommand']);

        self::assertTrue($cache->has());
    }

    #[Test]
    public function writeCreatesFile(): void
    {
        $path = $this->tempDir . '/cache.php';
        $cache = new CommandCache($path);

        $cache->write(['greet' => 'App\\GreetCommand']);

        self::assertFileExists($path);
    }

    #[Test]
    public function writeCreatesParentDirectoryIfMissing(): void
    {
        $path = $this->tempDir . '/nested/deep/cache.php';
        $cache = new CommandCache($path);

        $cache->write(['greet' => 'App\\GreetCommand']);

        self::assertDirectoryExists($this->tempDir . '/nested/deep');
        self::assertFileExists($path);
    }

    #[Test]
    public function readReturnsNullWhenFileDoesNotExist(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        self::assertNull($cache->read());
    }

    #[Test]
    public function readReturnsArrayAfterWrite(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        $cache->write(['greet' => 'App\\GreetCommand']);

        $result = $cache->read();

        self::assertIsArray($result);
        self::assertSame(['greet' => 'App\\GreetCommand'], $result);
    }

    #[Test]
    public function clearDeletesFile(): void
    {
        $path = $this->tempDir . '/cache.php';
        $cache = new CommandCache($path);

        $cache->write(['greet' => 'App\\GreetCommand']);
        self::assertFileExists($path);

        $cache->clear();
        self::assertFileDoesNotExist($path);
    }

    #[Test]
    public function clearDoesNothingWhenFileDoesNotExist(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        $cache->clear();

        self::assertFalse($cache->has());
    }

    #[Test]
    public function roundTripPreservesData(): void
    {
        $cache = new CommandCache($this->tempDir . '/cache.php');

        $data = [
            'greet' => 'App\\GreetCommand',
            'math:add' => 'App\\MathAddCommand',
            'dep:test' => 'App\\DependencyCommand',
        ];

        $cache->write($data);

        self::assertSame($data, $cache->read());
    }

    #[Test]
    public function writtenFileContainsValidPhpWithDeclareStrictTypes(): void
    {
        $path = $this->tempDir . '/cache.php';
        $cache = new CommandCache($path);

        $cache->write(['greet' => 'App\\GreetCommand']);

        $contents = file_get_contents($path);
        self::assertIsString($contents);
        self::assertStringContainsString('<?php', $contents);
        self::assertStringContainsString('declare(strict_types=1)', $contents);
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
