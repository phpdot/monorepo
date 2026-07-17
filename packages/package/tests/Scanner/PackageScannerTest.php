<?php

declare(strict_types=1);

namespace PHPdot\Package\Tests\Scanner;

use PHPdot\Container\Scope;
use PHPdot\Package\Scanner\PackageMeta;
use PHPdot\Package\Scanner\PackageScanner;
use PHPdot\Package\Scanner\ScanResult;
use PHPdot\Package\Tests\Fixtures\AbstractService;
use PHPdot\Package\Tests\Fixtures\BuiltinFirstService;
use PHPdot\Package\Tests\Fixtures\InstallHookWithoutHandler;
use PHPdot\Package\Tests\Fixtures\IntersectionService;
use PHPdot\Package\Tests\Fixtures\NoAttributeClass;
use PHPdot\Package\Tests\Fixtures\OptionalConcreteService;
use PHPdot\Package\Tests\Fixtures\OptionalDependencyService;
use PHPdot\Package\Tests\Fixtures\SampleConfig;
use PHPdot\Package\Tests\Fixtures\SampleInstallHook;
use PHPdot\Package\Tests\Fixtures\SampleInterface;
use PHPdot\Package\Tests\Fixtures\SampleLoader;
use PHPdot\Package\Tests\Fixtures\SampleService;
use PHPdot\Package\Tests\Fixtures\SimpleService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PackageScannerTest extends TestCase
{
    private PackageScanner $scanner;
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->scanner = new PackageScanner();
        $this->fixturesDir = __DIR__ . '/../Fixtures';
    }

    #[Test]
    public function it_scans_singleton(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SimpleService::class);

        self::assertNotNull($found);
        self::assertSame(Scope::SINGLETON, $found->scope);
    }

    #[Test]
    public function it_scans_scoped(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleService::class);

        self::assertNotNull($found);
        self::assertSame(Scope::SCOPED, $found->scope);
    }

    #[Test]
    public function it_scans_config_as_singleton(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleConfig::class);

        self::assertNotNull($found);
        self::assertSame('sample', $found->configName);
        self::assertSame(Scope::SINGLETON, $found->scope);
    }

    #[Test]
    public function it_scans_binds(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleLoader::class);

        self::assertNotNull($found);
        self::assertContains(SampleInterface::class, $found->binds);
    }

    #[Test]
    public function it_scans_install_hook_only_class(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleInstallHook::class);

        self::assertNotNull($found);
        self::assertTrue($found->installHook);
        self::assertNull($found->scope);
    }

    #[Test]
    public function it_ignores_install_hook_without_handler_interface(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, InstallHookWithoutHandler::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_reads_constructor_params(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleService::class);

        self::assertNotNull($found);
        self::assertContains(SampleInterface::class, $found->params);
        self::assertContains(SampleConfig::class, $found->params);
    }

    #[Test]
    public function it_skips_builtin_types(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleConfig::class);

        self::assertNotNull($found);
        self::assertSame([], $found->params);
    }

    #[Test]
    public function it_skips_classes_without_scope_attributes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, NoAttributeClass::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_skips_abstract_classes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, AbstractService::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_skips_interfaces(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleInterface::class);

        self::assertNull($found);
    }

    #[Test]
    public function it_stores_package_name(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleLoader::class);

        self::assertNotNull($found);
        self::assertSame('test/pkg', $found->package);
    }

    #[Test]
    public function it_returns_empty_scan_result_for_missing_installed_json(): void
    {
        $result = $this->scanner->scan('/nonexistent/vendor');

        self::assertInstanceOf(ScanResult::class, $result);
        self::assertSame([], $result->classes);
        self::assertSame([], $result->packages);
    }

    #[Test]
    public function it_returns_empty_scan_result_for_empty_packages(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_test_' . uniqid();
        mkdir($tmpDir . '/composer', 0o755, true);
        file_put_contents($tmpDir . '/composer/installed.json', '{"packages": []}');

        $result = $this->scanner->scan($tmpDir);

        self::assertInstanceOf(ScanResult::class, $result);
        self::assertSame([], $result->classes);
        self::assertSame([], $result->packages);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    #[Test]
    public function it_parses_param_descriptions_for_config_classes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleConfig::class);

        self::assertNotNull($found);
        self::assertSame('Application name', $found->paramDescriptions['name']);
        self::assertSame('Server port number', $found->paramDescriptions['port']);
        self::assertSame('Enable debug mode', $found->paramDescriptions['debug']);
        self::assertSame('Resource tags', $found->paramDescriptions['tags']);
    }

    #[Test]
    public function it_does_not_parse_param_descriptions_for_non_config_classes(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, SampleService::class);

        self::assertNotNull($found);
        self::assertSame([], $found->paramDescriptions);
    }

    #[Test]
    public function scan_returns_scan_result_with_packages_meta(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_meta_' . uniqid();
        mkdir($tmpDir . '/composer', 0o755, true);

        $installed = [
            'packages' => [
                [
                    'name' => 'phpdot/i18n',
                    'description' => 'Internationalization with ICU MessageFormat.',
                    'homepage' => 'https://github.com/phpdot/i18n',
                    'authors' => [
                        ['name' => 'Omar Hamdan', 'email' => 'omar@phpdot.com'],
                    ],
                    'require-dev' => ['phpdot/container' => '^1.2'],
                    'install-path' => '../nonexistent',
                    'autoload' => ['psr-4' => ['PHPdot\\I18n\\' => 'src/']],
                ],
            ],
        ];

        file_put_contents(
            $tmpDir . '/composer/installed.json',
            json_encode($installed, JSON_THROW_ON_ERROR),
        );

        $result = $this->scanner->scan($tmpDir);

        self::assertInstanceOf(ScanResult::class, $result);
        self::assertArrayHasKey('phpdot/i18n', $result->packages);

        $meta = $result->packages['phpdot/i18n'];
        self::assertInstanceOf(PackageMeta::class, $meta);
        self::assertSame('phpdot/i18n', $meta->name);
        self::assertSame('Internationalization with ICU MessageFormat.', $meta->description);
        self::assertSame('https://github.com/phpdot/i18n', $meta->url);
        self::assertSame('Omar Hamdan <omar@phpdot.com>', $meta->author);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    #[Test]
    public function scan_extracts_url_from_support_source(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_url_' . uniqid();
        mkdir($tmpDir . '/composer', 0o755, true);

        $installed = [
            'packages' => [
                [
                    'name' => 'phpdot/test',
                    'description' => 'Test package.',
                    'support' => ['source' => 'https://github.com/phpdot/test'],
                    'homepage' => 'https://phpdot.com/test',
                    'require-dev' => ['phpdot/container' => '^1.0'],
                    'install-path' => '../nonexistent',
                    'autoload' => ['psr-4' => ['PHPdot\\Test\\' => 'src/']],
                ],
            ],
        ];

        file_put_contents(
            $tmpDir . '/composer/installed.json',
            json_encode($installed, JSON_THROW_ON_ERROR),
        );

        $result = $this->scanner->scan($tmpDir);
        $meta = $result->packages['phpdot/test'];

        self::assertSame('https://github.com/phpdot/test', $meta->url);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    #[Test]
    public function scan_defaults_to_empty_strings_for_missing_metadata(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_empty_' . uniqid();
        mkdir($tmpDir . '/composer', 0o755, true);

        $installed = [
            'packages' => [
                [
                    'name' => 'phpdot/minimal',
                    'require' => ['phpdot/container' => '^1.0'],
                    'install-path' => '../nonexistent',
                    'autoload' => ['psr-4' => ['PHPdot\\Minimal\\' => 'src/']],
                ],
            ],
        ];

        file_put_contents(
            $tmpDir . '/composer/installed.json',
            json_encode($installed, JSON_THROW_ON_ERROR),
        );

        $result = $this->scanner->scan($tmpDir);
        $meta = $result->packages['phpdot/minimal'];

        self::assertSame('', $meta->description);
        self::assertSame('', $meta->url);
        self::assertSame('', $meta->author);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    #[Test]
    public function scan_skips_excluded_packages(): void
    {
        $tmpDir = sys_get_temp_dir() . '/phpdot_scanner_exclude_' . uniqid();
        mkdir($tmpDir . '/composer', 0o755, true);

        $installed = [
            'packages' => [
                [
                    'name' => 'phpdot/i18n',
                    'description' => 'I18n package.',
                    'require-dev' => ['phpdot/container' => '^1.2'],
                    'install-path' => '../nonexistent',
                    'autoload' => ['psr-4' => ['PHPdot\\I18n\\' => 'src/']],
                ],
                [
                    'name' => 'phpdot/cache',
                    'description' => 'Cache package.',
                    'require-dev' => ['phpdot/container' => '^1.0'],
                    'install-path' => '../nonexistent',
                    'autoload' => ['psr-4' => ['PHPdot\\Cache\\' => 'src/']],
                ],
            ],
        ];

        file_put_contents(
            $tmpDir . '/composer/installed.json',
            json_encode($installed, JSON_THROW_ON_ERROR),
        );

        $result = $this->scanner->scan($tmpDir, ['phpdot/i18n']);

        self::assertArrayNotHasKey('phpdot/i18n', $result->packages);
        self::assertArrayHasKey('phpdot/cache', $result->packages);

        unlink($tmpDir . '/composer/installed.json');
        rmdir($tmpDir . '/composer');
        rmdir($tmpDir);
    }

    #[Test]
    public function it_resolves_intersection_type_params_in_order(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, IntersectionService::class);

        self::assertNotNull($found);
        self::assertSame(
            ['factory' => SampleInterface::class, 'next' => SimpleService::class],
            $found->params,
        );
    }

    #[Test]
    public function it_skips_builtin_params_keeping_named_alignment(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, BuiltinFirstService::class);

        self::assertNotNull($found);
        self::assertSame(['service' => SimpleService::class], $found->params);
    }

    #[Test]
    public function it_skips_optional_params_with_non_constructible_types(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, OptionalDependencyService::class);

        self::assertNotNull($found);
        self::assertSame(['service' => SimpleService::class], $found->params);
    }

    #[Test]
    public function it_injects_optional_params_with_constructible_types(): void
    {
        $results = $this->scanner->scanDirectory($this->fixturesDir, 'PHPdot\\Package\\Tests\\Fixtures\\', 'test/pkg');
        $found = $this->findByClass($results, OptionalConcreteService::class);

        self::assertNotNull($found);
        self::assertSame(
            ['required' => SampleInterface::class, 'optional' => SimpleService::class],
            $found->params,
        );
    }

    /**
     * @param list<\PHPdot\Package\Scanner\ScannedClass> $results
     */
    private function findByClass(array $results, string $class): ?\PHPdot\Package\Scanner\ScannedClass
    {
        foreach ($results as $scanned) {
            if ($scanned->class === $class) {
                return $scanned;
            }
        }

        return null;
    }
}
