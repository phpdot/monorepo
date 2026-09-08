<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Unit;

use PHPdot\Config\Configuration;
use PHPdot\Path\PathInstaller;
use PHPdot\Path\PathRegistry;
use PHPdot\Path\Tests\Fixtures\PathProbeConfig;
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
        $this->removeTree($this->dir);
    }

    private function removeTree(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $entry) {
            if (is_dir($entry)) {
                $this->removeTree($entry);
            } else {
                @unlink($entry);
            }
        }

        @rmdir($dir);
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
    public function it_writes_a_portable_dirname_expression_inside_the_root(): void
    {
        $root = $this->dir . '/app';
        $configDir = $root . '/config';
        mkdir($configDir, 0o755, true);
        file_put_contents(
            $configDir . '/path.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'base' => '',\n    'public' => '{path.base}/public',\n];\n",
        );

        $message = PathInstaller::install($root, $configDir);

        self::assertSame("phpdot/path: set base to {$root}", $message);

        $content = (string) file_get_contents($configDir . '/path.php');
        self::assertStringContainsString("'base' => dirname(__DIR__, 1)", $content, 'the walk, not a frozen literal');

        $canonicalRoot = (string) realpath($root);
        $config = require $configDir . '/path.php';
        self::assertSame($canonicalRoot, $config['base'], 'the expression evaluates to the root from where the file lives');

        self::assertNull(PathInstaller::install($root, $configDir), 'idempotent: the written form no longer matches');
    }

    #[Test]
    public function an_installed_base_carries_placeholders_into_sibling_sections(): void
    {
        $root = $this->dir . '/app';
        $configDir = $root . '/config';
        mkdir($configDir, 0o755, true);
        file_put_contents(
            $configDir . '/path.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'base' => '',\n    'protected' => '{path.base}/protected',\n];\n",
        );
        file_put_contents(
            $configDir . '/template.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'templates' => '{path.protected}/Templates',\n];\n",
        );

        PathInstaller::install($root, $configDir);

        $canonicalRoot = (string) realpath($root);
        $config = new Configuration($configDir, 'production');

        $sibling = $config->dto('template', PathProbeConfig::class);
        self::assertSame($canonicalRoot . '/protected/Templates', $sibling->templates, 'siblings see the full absolute path, not a filesystem-root one');

        self::assertSame($canonicalRoot . '/protected', (new PathRegistry($config))->protected());
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
