<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit;

use PHPdot\Bun\BunInstaller;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BunInstallerTest extends TestCase
{
    private string $root;

    private string $configDir;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/phpdot-bun-install-' . bin2hex(random_bytes(4));
        $this->configDir = $this->root . '/config';
        mkdir($this->configDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->configDir . '/bun.php');
        @rmdir($this->configDir);
        rmdir($this->root);
    }

    #[Test]
    public function fillsTheScaffoldedEmptiesWithPortableAbsoluteExpressions(): void
    {
        $this->writeScaffold();

        $message = BunInstaller::install($this->root, $this->configDir);

        self::assertNotNull($message);
        self::assertStringContainsString('resourcesDir', $message);

        $written = (string) file_get_contents($this->configDir . '/bun.php');
        self::assertStringContainsString("'resourcesDir' => dirname(__DIR__) . '/' . 'resources'", $written, 'a portable expression, never a frozen literal');
        self::assertStringNotContainsString($this->root, $written, 'the machine path is not baked into the file');

        $config = require $this->configDir . '/bun.php';
        $root = (string) realpath($this->root);

        self::assertSame($root . '/resources', $config['resourcesDir'], 'the expression evaluates to the project root from config/bun.php');
        self::assertSame($root . '/public/build', $config['outputDir']);
        self::assertSame('https://registry.npmjs.org', $config['registryUrl'], 'non-directory keys are untouched');
    }

    #[Test]
    public function isIdempotentOverItsOwnWriting(): void
    {
        $this->writeScaffold();
        BunInstaller::install($this->root, $this->configDir);

        self::assertNull(BunInstaller::install($this->root, $this->configDir), 'the written expressions no longer match the empty literal');
    }

    #[Test]
    public function leavesValuesTheDeveloperAlreadySet(): void
    {
        file_put_contents(
            $this->configDir . '/bun.php',
            "<?php\n\nreturn ['resourcesDir' => '/srv/app/resources', 'outputDir' => ''];\n",
        );

        BunInstaller::install($this->root, $this->configDir);

        $config = require $this->configDir . '/bun.php';

        self::assertSame('/srv/app/resources', $config['resourcesDir'], 'a set value is never overwritten');
    }

    #[Test]
    public function returnsNullWhenThereIsNoConfig(): void
    {
        @unlink($this->configDir . '/bun.php');

        self::assertNull(BunInstaller::install($this->root, $this->configDir));
    }

    private function writeScaffold(): void
    {
        file_put_contents(
            $this->configDir . '/bun.php',
            "<?php\n\ndeclare(strict_types=1);\n\nreturn [\n    'pinnedVersion' => '1.4.0',\n    'registryUrl' => 'https://registry.npmjs.org',\n    'resourcesDir' => '',\n    'outputDir' => '',\n    'baseUrl' => null,\n];\n",
        );
    }
}
