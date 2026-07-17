<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Handler;

use PHPdot\Session\Exception\SessionWriteException;
use PHPdot\Session\Handler\FileHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileHandlerTest extends TestCase
{
    private string $directory;
    private FileHandler $handler;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpdot_session_test_' . bin2hex(random_bytes(8));
        $this->handler = new FileHandler($this->directory);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $files = glob($this->directory . '/*');

            if ($files !== false) {
                foreach ($files as $file) {
                    @unlink($file);
                }
            }

            @rmdir($this->directory);
        }
    }

    #[Test]
    public function readReturnsEmptyForNonexistent(): void
    {
        self::assertSame('', $this->handler->read('nonexistent'));
    }

    #[Test]
    public function writeAndReadRoundTrip(): void
    {
        $this->handler->write('id1', '{"key":"value"}', 3600);

        self::assertSame('{"key":"value"}', $this->handler->read('id1'));
    }

    #[Test]
    public function destroyRemovesFile(): void
    {
        $this->handler->write('id1', 'data', 3600);

        $this->handler->destroy('id1');

        self::assertSame('', $this->handler->read('id1'));
    }

    #[Test]
    public function destroyNonexistentIsSafe(): void
    {
        $this->handler->destroy('nonexistent');

        self::assertFalse($this->handler->exists('nonexistent'));
    }

    #[Test]
    public function existsChecksFileExistence(): void
    {
        self::assertFalse($this->handler->exists('id1'));

        $this->handler->write('id1', 'data', 3600);

        self::assertTrue($this->handler->exists('id1'));
    }

    #[Test]
    public function createsDirectoryIfNotExists(): void
    {
        self::assertDirectoryDoesNotExist($this->directory);

        $this->handler->write('id1', 'data', 3600);

        self::assertDirectoryExists($this->directory);
    }

    #[Test]
    public function gcRemovesOldFiles(): void
    {
        $this->handler->write('old', 'data', 3600);

        $path = $this->directory . '/sess_old';
        touch($path, time() - 7201);

        $count = $this->handler->gc(3600);

        self::assertSame(1, $count);
        self::assertFalse($this->handler->exists('old'));
    }

    #[Test]
    public function gcReturnsCorrectCount(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->handler->write("old_{$i}", 'data', 3600);
            touch($this->directory . "/sess_old_{$i}", time() - 7201);
        }

        $this->handler->write('fresh', 'data', 3600);

        self::assertSame(3, $this->handler->gc(3600));
    }

    #[Test]
    public function gcSkipsFreshFiles(): void
    {
        $this->handler->write('fresh', 'data', 3600);

        self::assertSame(0, $this->handler->gc(3600));
        self::assertTrue($this->handler->exists('fresh'));
    }

    #[Test]
    public function gcHandlesMissingDirectory(): void
    {
        $handler = new FileHandler('/nonexistent/path');

        self::assertSame(0, $handler->gc(3600));
    }

    #[Test]
    public function gcWithZeroLifetimePreservesFiles(): void
    {
        // Regression: gc(0) used to compute cutoff = time() and delete every
        // file with mtime < now, wiping all sessions on a lifetime=0 config.
        $this->handler->write('id1', 'data', 0);
        $this->handler->write('id2', 'data', 0);

        // Force mtime strictly less than the current second.
        touch($this->directory . '/sess_id1', time() - 10);
        touch($this->directory . '/sess_id2', time() - 10);

        self::assertSame(0, $this->handler->gc(0));
        self::assertTrue($this->handler->exists('id1'));
        self::assertTrue($this->handler->exists('id2'));
    }

    #[Test]
    public function gcWithNegativeLifetimePreservesFiles(): void
    {
        $this->handler->write('id1', 'data', 0);
        touch($this->directory . '/sess_id1', time() - 10);

        self::assertSame(0, $this->handler->gc(-1));
        self::assertTrue($this->handler->exists('id1'));
    }

    #[Test]
    public function overwriteExisting(): void
    {
        $this->handler->write('id1', 'old', 3600);
        $this->handler->write('id1', 'new', 3600);

        self::assertSame('new', $this->handler->read('id1'));
    }

    #[Test]
    public function writeEmptyData(): void
    {
        $this->handler->write('id1', '', 3600);

        self::assertSame('', $this->handler->read('id1'));
        self::assertTrue($this->handler->exists('id1'));
    }

    #[Test]
    public function pathUsesSessPrefix(): void
    {
        $this->handler->write('test_id', 'data', 3600);

        self::assertFileExists($this->directory . '/sess_test_id');
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(FileHandler::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function writeToUnwritableDirectoryThrows(): void
    {
        $handler = new FileHandler('/proc/nonexistent/sessions');

        $this->expectException(SessionWriteException::class);

        $handler->write('id1', 'data', 3600);
    }
}
