<?php

declare(strict_types=1);

/**
 * Pins what template:clear removes, what it leaves alone, and what it says in each state.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Template\Tests\Unit\Cli;

use PHPdot\Template\Cli\ClearCommand;
use PHPdot\Template\TemplateConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ClearCommandTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/phpdot_template_clear_' . uniqid();
    }

    protected function tearDown(): void
    {
        $this->remove($this->directory);
    }

    #[Test]
    public function removesCompiledTemplatesAndTheBucketsTheyEmpty(): void
    {
        $compiledA = $this->directory . '/ab/' . str_repeat('ab', 16) . '.php';
        $compiledB = $this->directory . '/cd/' . str_repeat('cd', 16) . '.php';
        $foreignInBucket = $this->directory . '/ab/notes.txt';
        $foreignSource = $this->directory . '/src/Foo.php';
        $foreignFile = $this->directory . '/keep.txt';

        foreach ([$compiledA, $compiledB, $foreignInBucket, $foreignSource, $foreignFile] as $file) {
            $this->touch($file);
        }

        $tester = $this->execute($this->directory);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertFileDoesNotExist($compiledA);
        self::assertFileDoesNotExist($compiledB);
        self::assertDirectoryDoesNotExist($this->directory . '/cd');
        self::assertFileExists($foreignInBucket);
        self::assertFileExists($foreignSource);
        self::assertFileExists($foreignFile);
        self::assertDirectoryExists($this->directory);
        self::assertStringContainsString('2 compiled templates removed', $tester->getDisplay());
        self::assertStringContainsString('restart', $tester->getDisplay());
    }

    #[Test]
    public function reportsWhenTheCacheIsAlreadyEmpty(): void
    {
        mkdir($this->directory, 0o777, true);

        $tester = $this->execute($this->directory);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('already empty', $tester->getDisplay());
    }

    #[Test]
    public function reportsWhenTheCacheDirectoryDoesNotExistYet(): void
    {
        $tester = $this->execute($this->directory);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertDirectoryDoesNotExist($this->directory);
        self::assertStringContainsString('already empty', $tester->getDisplay());
    }

    #[Test]
    public function reportsWhenCachingIsDisabled(): void
    {
        $tester = $this->execute(null);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('disabled', $tester->getDisplay());
    }

    #[Test]
    public function treatsAnEmptyCachePathAsDisabled(): void
    {
        $tester = $this->execute('');

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('disabled', $tester->getDisplay());
    }

    private function execute(null|string $cache): CommandTester
    {
        $tester = new CommandTester(new ClearCommand(new TemplateConfig(cache: $cache)));
        $tester->execute([]);

        return $tester;
    }

    private function touch(string $file): void
    {
        $parent = dirname($file);

        if (!is_dir($parent)) {
            mkdir($parent, 0o777, true);
        }

        file_put_contents($file, '');
    }

    private function remove(string $path): void
    {
        if (is_file($path)) {
            unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);

        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->remove($path . '/' . $entry);
            }
        }

        rmdir($path);
    }
}
