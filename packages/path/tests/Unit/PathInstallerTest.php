<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Unit;

use PHPdot\Path\PathInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PathInstallerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/phpdot-path-install-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir . '/*') ?: []);
        @rmdir($this->dir);
    }

    #[Test]
    public function it_fills_an_empty_base_with_the_root(): void
    {
        file_put_contents(
            $this->dir . '/path.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'base' => '',\n    'config' => '{path.base}/config',\n];\n",
        );

        $message = PathInstaller::install('/abs/root', $this->dir);

        self::assertSame('phpdot/path: set base to /abs/root', $message);

        $config = require $this->dir . '/path.php';
        self::assertSame('/abs/root', $config['base']);
        self::assertSame('{path.base}/config', $config['config']);
    }

    #[Test]
    public function it_leaves_an_already_set_base_untouched(): void
    {
        file_put_contents($this->dir . '/path.php', "<?php\n\nreturn ['base' => '/existing'];\n");

        self::assertNull(PathInstaller::install('/abs/root', $this->dir));
    }

    #[Test]
    public function it_returns_null_when_the_config_is_missing(): void
    {
        self::assertNull(PathInstaller::install('/abs/root', $this->dir));
    }
}
