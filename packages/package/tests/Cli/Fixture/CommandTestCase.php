<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Cli\Fixture;

use FilesystemIterator;
use PHPdot\Package\PackageManager;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

abstract class CommandTestCase extends TestCase
{
    protected string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/phpdot_cli_' . uniqid();

        mkdir($this->basePath . '/vendor/composer', 0o755, true);
        mkdir($this->basePath . '/vendor/phpdot', 0o755, true);
        mkdir($this->basePath . '/config', 0o755, true);

        file_put_contents(
            $this->basePath . '/vendor/composer/installed.json',
            '{"packages": []}',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->basePath);
    }

    protected function getManager(): PackageManager
    {
        return new PackageManager($this->basePath);
    }

    protected function writeManifest(string $contents): void
    {
        file_put_contents($this->basePath . '/vendor/phpdot/manifest.php', $contents);
    }

    protected function writeStandardManifest(): void
    {
        $this->writeManifest(<<<'PHP'
            <?php

            return [
                'generated_at' => '2026-04-28T12:00:00+00:00',
                'ownedConfigs' => [],
                'packages' => [
                    'phpdot/http' => [
                        'description' => 'HTTP package.',
                        'url' => 'https://github.com/phpdot/http',
                        'author' => 'Test <test@example.com>',
                        'services' => [
                            'PHPdot\\Http\\ResponseFactory' => 'SINGLETON',
                            'PHPdot\\Http\\HttpConfig' => 'SINGLETON',
                        ],
                        'configs' => [
                            'PHPdot\\Http\\HttpConfig' => 'http',
                        ],
                        'bindings' => [
                            'Psr\\Http\\Message\\ResponseFactoryInterface' => 'PHPdot\\Http\\ResponseFactory',
                        ],
                    ],
                    'phpdot/console' => [
                        'description' => 'Console framework.',
                        'url' => 'https://github.com/phpdot/console',
                        'author' => 'Test <test@example.com>',
                        'services' => [
                            'PHPdot\\Console\\ConsoleConfig' => 'SINGLETON',
                        ],
                        'configs' => [
                            'PHPdot\\Console\\ConsoleConfig' => 'console',
                        ],
                        'bindings' => [],
                    ],
                ],
            ];
            PHP);
    }

    protected function writeConfigFile(string $name): void
    {
        file_put_contents($this->basePath . '/config/' . $name . '.php', '<?php return [];');
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($dir);
    }
}
