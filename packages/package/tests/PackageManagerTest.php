<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests;

use PHPdot\Container\ContainerBuilder;
use PHPdot\Package\Manifest;
use PHPdot\Package\PackageManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageManagerTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/phpdot_mgr_' . uniqid();

        mkdir($this->basePath . '/vendor/composer', 0o755, true);
        file_put_contents(
            $this->basePath . '/vendor/composer/installed.json',
            '{"packages": []}',
        );
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->basePath);
    }

    #[Test]
    public function it_creates_phpdot_directory(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        self::assertDirectoryExists($this->basePath . '/vendor/phpdot');
    }

    #[Test]
    public function render_config_returns_empty_for_an_uninstalled_package(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertSame([], $manager->renderConfig('phpdot/nonexistent'));
    }

    #[Test]
    public function it_writes_definitions_file(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        self::assertFileExists($manager->definitionsPath());

        $content = file_get_contents($manager->definitionsPath());
        self::assertIsString($content);
        self::assertStringContainsString('return [', $content);
    }

    #[Test]
    public function it_writes_manifest_file(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        self::assertFileExists($manager->manifestPath());

        $content = file_get_contents($manager->manifestPath());
        self::assertIsString($content);
        self::assertStringContainsString('generated_at', $content);
    }

    #[Test]
    public function it_returns_correct_counts(): void
    {
        $manager = new PackageManager($this->basePath);
        $result = $manager->rebuild();

        self::assertSame(0, $result->packageCount);
        self::assertSame(0, $result->serviceCount);
        self::assertSame(0, $result->bindingCount);
        self::assertSame(0, $result->configCount);
        self::assertSame([], $result->generatedConfigs);
    }

    #[Test]
    public function it_clears_definitions_and_manifest(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        self::assertFileExists($manager->definitionsPath());
        self::assertFileExists($manager->manifestPath());

        $manager->clear();

        self::assertFileDoesNotExist($manager->definitionsPath());
        self::assertFileDoesNotExist($manager->manifestPath());
    }

    #[Test]
    public function it_clear_is_silent_when_no_files(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->clear();

        self::assertFileDoesNotExist($manager->definitionsPath());
    }

    #[Test]
    public function it_returns_null_manifest_when_not_built(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertNull($manager->manifest());
    }

    #[Test]
    public function it_returns_manifest_after_rebuild(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        $manifest = $manager->manifest();

        self::assertInstanceOf(Manifest::class, $manifest);
        self::assertSame([], $manifest->packageNames());
    }

    #[Test]
    public function it_defaults_paths_when_no_composer_json(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath, $manager->basePath());
        self::assertSame($this->basePath . '/vendor', $manager->vendorPath());
        self::assertSame($this->basePath . '/config', $manager->configPath());
    }

    #[Test]
    public function it_reads_vendor_dir_from_composer_json(): void
    {
        $composer = ['config' => ['vendor-dir' => 'libs']];
        file_put_contents(
            $this->basePath . '/composer.json',
            json_encode($composer, JSON_THROW_ON_ERROR),
        );

        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/libs', $manager->vendorPath());
    }

    #[Test]
    public function it_reads_config_dir_from_composer_json_extra(): void
    {
        $composer = ['extra' => ['phpdot' => ['config-dir' => 'settings']]];
        file_put_contents(
            $this->basePath . '/composer.json',
            json_encode($composer, JSON_THROW_ON_ERROR),
        );

        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/settings', $manager->configPath());
    }

    #[Test]
    public function it_defaults_when_composer_json_has_no_extra(): void
    {
        $composer = ['name' => 'test/app'];
        file_put_contents(
            $this->basePath . '/composer.json',
            json_encode($composer, JSON_THROW_ON_ERROR),
        );

        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/vendor', $manager->vendorPath());
        self::assertSame($this->basePath . '/config', $manager->configPath());
    }

    #[Test]
    public function load_returns_builder_with_definitions_when_file_exists(): void
    {
        $manager = new PackageManager($this->basePath);
        $manager->rebuild();

        $builder = new ContainerBuilder();
        $result = $manager->load($builder);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function load_returns_builder_unchanged_when_no_definitions_file(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertFileDoesNotExist($manager->definitionsPath());

        $builder = new ContainerBuilder();
        $result = $manager->load($builder);

        self::assertSame($builder, $result);
    }

    #[Test]
    public function it_returns_correct_definitions_path(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/vendor/phpdot/definitions.php', $manager->definitionsPath());
    }

    #[Test]
    public function it_returns_correct_manifest_path(): void
    {
        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/vendor/phpdot/manifest.php', $manager->manifestPath());
    }

    #[Test]
    public function it_reads_exclude_from_composer_json(): void
    {
        $composer = [
            'extra' => ['phpdot' => ['exclude' => ['phpdot/i18n', 'phpdot/cache']]],
        ];
        file_put_contents(
            $this->basePath . '/composer.json',
            json_encode($composer, JSON_THROW_ON_ERROR),
        );

        $manager = new PackageManager($this->basePath);
        $result = $manager->rebuild();

        self::assertSame(0, $result->packageCount);
    }

    #[Test]
    public function rebuild_resolves_orphan_configs_from_relative_names(): void
    {
        $configDir = $this->basePath . '/config';
        mkdir($configDir, 0o755, true);
        file_put_contents($configDir . '/sample.php', '<?php return [];');

        $manager = new PackageManager($this->basePath);

        // Seed a previous manifest that records the owned config by name only —
        // the portable format. No installed package owns it now, so the next
        // rebuild must flag it as an orphan, resolving the relative name to an
        // absolute path via the current config dir.
        mkdir(dirname($manager->manifestPath()), 0o755, true);
        file_put_contents(
            $manager->manifestPath(),
            "<?php\n\nreturn ['ownedConfigs' => ['sample.php'], 'packages' => []];\n",
        );

        $result = $manager->rebuild();

        self::assertSame([$configDir . '/sample.php'], $result->orphanedConfigs);
    }

    #[Test]
    public function rebuild_resolves_orphan_configs_in_nested_subdirectories(): void
    {
        $configDir = $this->basePath . '/config';
        mkdir($configDir . '/database', 0o755, true);
        file_put_contents($configDir . '/database/mysql.php', '<?php return [];');

        $manager = new PackageManager($this->basePath);

        // Seed a previous manifest recording a nested config by its relative
        // subdirectory path. No installed package owns it now, so rebuild must
        // resolve the relative path under the config dir and flag it orphaned.
        mkdir(dirname($manager->manifestPath()), 0o755, true);
        file_put_contents(
            $manager->manifestPath(),
            "<?php\n\nreturn ['ownedConfigs' => ['database/mysql.php'], 'packages' => []];\n",
        );

        $result = $manager->rebuild();

        self::assertSame([$configDir . '/database/mysql.php'], $result->orphanedConfigs);
    }

    #[Test]
    public function config_dir_is_read_from_root_composer_json_with_nested_vendor(): void
    {
        // Root composer.json declares a relocated vendor-dir and a custom
        // config-dir; both must resolve relative to the project root.
        file_put_contents($this->basePath . '/composer.json', json_encode([
            'config' => ['vendor-dir' => 'protected/vendor'],
            'extra' => ['phpdot' => ['config-dir' => 'app/config']],
        ], JSON_THROW_ON_ERROR));

        $manager = new PackageManager($this->basePath);

        self::assertSame($this->basePath . '/protected/vendor', $manager->vendorPath());
        self::assertSame($this->basePath . '/app/config', $manager->configPath());
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
