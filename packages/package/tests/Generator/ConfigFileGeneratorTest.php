<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Generator;

use PHPdot\Container\Scope;
use PHPdot\Package\Generator\ConfigFileGenerator;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\ScannedClass;
use PHPdot\Package\Tests\Fixtures\MapConfig;
use PHPdot\Package\Tests\Fixtures\OuterConfig;
use PHPdot\Package\Tests\Fixtures\SampleConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfigFileGeneratorTest extends TestCase
{
    private ConfigFileGenerator $generator;
    private string $tmpDir;

    /** @var array<string, PackageMeta> */
    private array $packages;

    protected function setUp(): void
    {
        $this->generator = new ConfigFileGenerator();
        $this->tmpDir = sys_get_temp_dir() . '/phpdot_cfggen_' . uniqid();
        $this->packages = [
            'test/pkg' => new PackageMeta(
                name: 'test/pkg',
                description: 'A test package for unit testing.',
                url: 'https://github.com/test/pkg',
                author: 'Test Author <test@example.com>',
            ),
        ];
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    #[Test]
    public function it_generates_config_file(): void
    {
        $generated = $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        self::assertCount(1, $generated);
        self::assertFileExists($this->tmpDir . '/sample.php');
    }

    #[Test]
    public function it_generates_nested_config_file_creating_subdirectories(): void
    {
        $nested = new ScannedClass(
            SampleConfig::class,
            Scope::SINGLETON,
            [],
            [],
            'database/mysql',
            'test/pkg',
        );

        $generated = $this->generator->generate([$nested], $this->packages, $this->tmpDir);

        self::assertCount(1, $generated);
        self::assertFileExists($this->tmpDir . '/database/mysql.php');
        self::assertSame($this->tmpDir . '/database/mysql.php', $generated[0]);
    }

    #[Test]
    public function it_contains_parameter_keys_with_defaults(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString("'name' => 'default'", $content);
        self::assertStringContainsString("'port' => 3000", $content);
        self::assertStringContainsString("'debug' => false", $content);
        self::assertStringContainsString("'tags' => []", $content);
    }

    #[Test]
    public function it_uses_phpdoc_descriptions_not_type_names(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Application name', $content);
        self::assertStringContainsString('Server port number', $content);
        self::assertStringContainsString('Enable debug mode', $content);
        self::assertStringContainsString('Resource tags', $content);
        self::assertStringNotContainsString('// (string)', $content);
        self::assertStringNotContainsString('// (int)', $content);
    }

    #[Test]
    public function it_uses_docblock_comments(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('/**', $content);
        self::assertStringContainsString('*/', $content);
        self::assertStringNotContainsString('// (', $content);
    }

    #[Test]
    public function it_has_declare_strict_types(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('declare(strict_types=1);', $content);
    }

    #[Test]
    public function it_has_professional_docblock_header(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('@package     test/pkg', $content);
        self::assertStringContainsString('@see         https://github.com/test/pkg', $content);
        self::assertStringContainsString('@see         phpdot/config', $content);
        self::assertStringContainsString('@generated   phpdot/package', $content);
        self::assertStringNotContainsString('@author', $content);
    }

    #[Test]
    public function it_includes_package_description_in_header(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('A test package for unit testing.', $content);
    }

    #[Test]
    public function it_includes_cli_commands_in_header(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('php dot package:config test/pkg', $content);
        self::assertStringContainsString('php dot package:show test/pkg', $content);
        self::assertStringNotContainsString('package:reset', $content);
        self::assertStringNotContainsString('package:config sample', $content);
    }

    #[Test]
    public function it_renders_config_content_without_writing(): void
    {
        $content = $this->generator->render($this->configClass(), $this->packages['test/pkg']);

        self::assertStringContainsString('return [', $content);
        self::assertStringContainsString("'name' => 'default'", $content);
        self::assertStringContainsString('php dot package:config test/pkg', $content);
        self::assertFileDoesNotExist($this->tmpDir . '/sample.php');
    }

    #[Test]
    public function it_includes_ownership_notice(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString("This is your file", $content);
        self::assertStringContainsString("we won't touch it", $content);
    }

    #[Test]
    public function it_includes_environment_override_block(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Environment overrides', $content);
        self::assertStringContainsString("'development'", $content);
        self::assertStringContainsString("'production'", $content);
        self::assertStringContainsString("'staging'", $content);
    }

    #[Test]
    public function it_has_all_environment_blocks_empty(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString("'development' => [\n    ],", $content);
        self::assertStringContainsString("'production' => [\n    ],", $content);
        self::assertStringContainsString("'staging' => [\n    ],", $content);
    }

    #[Test]
    public function it_uses_configurable_environments(): void
    {
        $this->generator->generate(
            [$this->configClass()],
            $this->packages,
            $this->tmpDir,
            ['local', 'production'],
        );

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString("'local'", $content);
        self::assertStringContainsString("'production'", $content);
        self::assertStringNotContainsString("'development'", $content);
        self::assertStringNotContainsString("'staging'", $content);
    }

    #[Test]
    public function it_has_no_blank_lines_between_config_keys(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString("'name' => 'default'," . "\n" . '    /**', $content);
        self::assertStringContainsString("'port' => 3000," . "\n" . '    /**', $content);
    }

    #[Test]
    public function it_skips_existing_config_files(): void
    {
        mkdir($this->tmpDir, 0o755, true);
        file_put_contents($this->tmpDir . '/sample.php', '<?php return [];');

        $generated = $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        self::assertSame([], $generated);

        $content = file_get_contents($this->tmpDir . '/sample.php');
        self::assertSame('<?php return [];', $content);
    }

    #[Test]
    public function it_skips_non_config_classes(): void
    {
        $classes = [
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'test/pkg'),
        ];

        $generated = $this->generator->generate($classes, $this->packages, $this->tmpDir);

        self::assertSame([], $generated);
    }

    #[Test]
    public function it_creates_config_directory(): void
    {
        $deepDir = $this->tmpDir . '/nested/config';

        $this->generator->generate([$this->configClass()], $this->packages, $deepDir);

        self::assertDirectoryExists($deepDir);
        self::assertFileExists($deepDir . '/sample.php');
    }

    #[Test]
    public function it_generates_valid_php(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        /** @var array<string, mixed> $result */
        $result = require $this->tmpDir . '/sample.php';

        self::assertIsArray($result);
        self::assertArrayHasKey('name', $result);
        self::assertArrayHasKey('port', $result);
        self::assertSame('default', $result['name']);
        self::assertSame(3000, $result['port']);
    }

    #[Test]
    public function it_falls_back_to_humanized_name_when_no_phpdoc(): void
    {
        $class = new ScannedClass(
            SampleConfig::class,
            Scope::SINGLETON,
            [],
            [],
            'sample',
            'test/pkg',
            [],
        );

        $this->generator->generate([$class], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('Name', $content);
        self::assertStringContainsString('Port', $content);
    }

    #[Test]
    public function it_does_not_include_service_hint_block(): void
    {
        $classes = [
            $this->configClass(),
            new ScannedClass('App\\Svc', Scope::SCOPED, [], [], null, 'test/pkg'),
        ];

        $this->generator->generate($classes, $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringNotContainsString('Services:', $content);
        self::assertStringNotContainsString('container/services.php', $content);
    }

    #[Test]
    public function it_works_without_package_meta(): void
    {
        $this->generator->generate([$this->configClass()], [], $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('test/pkg', $content);
        self::assertStringContainsString('@package', $content);
    }

    #[Test]
    public function it_scaffolds_nested_dto_default_as_array(): void
    {
        $class = new ScannedClass(
            OuterConfig::class,
            Scope::SINGLETON,
            ['hosts' => 'array', 'inner' => 'PHPdot\\Package\\Tests\\Fixtures\\InnerConfig'],
            [],
            'outer',
            'test/pkg',
            [],
        );

        $this->generator->generate([$class], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('outer.php');

        // The nested DTO must NOT collapse to '' — it must scaffold the inner fields
        self::assertStringNotContainsString("'inner' => ''", $content);
        self::assertStringContainsString("'inner' => [", $content);
        self::assertStringContainsString("'secure' => true", $content);
        self::assertStringContainsString("'sameSite' => 'Lax'", $content);
        self::assertStringContainsString("'maxAge' => 3600", $content);
    }

    #[Test]
    public function nested_scaffold_produces_runnable_php(): void
    {
        $class = new ScannedClass(
            OuterConfig::class,
            Scope::SINGLETON,
            ['hosts' => 'array', 'inner' => 'PHPdot\\Package\\Tests\\Fixtures\\InnerConfig'],
            [],
            'outer',
            'test/pkg',
            [],
        );

        $this->generator->generate([$class], $this->packages, $this->tmpDir);

        /** @var array<string, mixed> $result */
        $result = require $this->tmpDir . '/outer.php';

        self::assertIsArray($result);
        self::assertArrayHasKey('inner', $result);
        self::assertIsArray($result['inner']);
        self::assertSame(true, $result['inner']['secure']);
        self::assertSame('Lax', $result['inner']['sameSite']);
        self::assertSame(3600, $result['inner']['maxAge']);
    }

    #[Test]
    public function it_scaffolds_keyed_nested_array_as_multiline(): void
    {
        $class = new ScannedClass(
            MapConfig::class,
            Scope::SINGLETON,
            [],
            [],
            'map',
            'test/pkg',
            [],
        );

        $this->generator->generate([$class], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('map.php');

        self::assertStringContainsString("'connections' => [\n", $content);
        self::assertStringContainsString("'primary' => [\n", $content);
        self::assertStringContainsString("'driver' => 'mysql'", $content);
        self::assertStringNotContainsString("'connections' => ['primary'", $content);
    }

    #[Test]
    public function it_includes_orphan_removal_note_in_header(): void
    {
        $this->generator->generate([$this->configClass()], $this->packages, $this->tmpDir);

        $content = $this->readGenerated('sample.php');
        self::assertStringContainsString('composer remove test/pkg', $content);
        self::assertStringContainsString('orphaned', $content);
    }

    #[Test]
    public function owned_paths_returns_full_set_regardless_of_existing_files(): void
    {
        // Create the file first to prove ownedPaths returns it even when it exists
        mkdir($this->tmpDir, 0o755, true);
        file_put_contents($this->tmpDir . '/sample.php', '<?php return [];');

        $paths = $this->generator->ownedPaths([$this->configClass()], $this->tmpDir);

        self::assertCount(1, $paths);
        self::assertSame($this->tmpDir . '/sample.php', $paths[0]);
    }

    #[Test]
    public function owned_paths_skips_classes_without_config_name(): void
    {
        $classes = [
            $this->configClass(),
            new ScannedClass('App\\Svc', Scope::SINGLETON, [], [], null, 'test/pkg'),
        ];

        $paths = $this->generator->ownedPaths($classes, $this->tmpDir);

        self::assertCount(1, $paths);
    }

    private function configClass(): ScannedClass
    {
        return new ScannedClass(
            SampleConfig::class,
            Scope::SINGLETON,
            [],
            [],
            'sample',
            'test/pkg',
            [
                'name' => 'Application name',
                'port' => 'Server port number',
                'debug' => 'Enable debug mode',
                'tags' => 'Resource tags',
            ],
        );
    }

    private function readGenerated(string $filename): string
    {
        $content = file_get_contents($this->tmpDir . '/' . $filename);
        self::assertIsString($content);

        return $content;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
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
