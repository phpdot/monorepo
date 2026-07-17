<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Unit;

use PHPdot\Package\Manifest;
use PHPdot\Package\PackageInfo;
use PHPdot\Template\EngineFactory;
use PHPdot\Template\TemplateConfig;
use PHPdot\Template\Tests\Stubs\ArrayContainer;
use PHPdot\Template\Tests\Stubs\GreetingExtension;
use PHPUnit\Framework\TestCase;
use Twig\Extension\DebugExtension;

final class EngineFactoryTest extends TestCase
{
    private string $viewsDir;

    protected function setUp(): void
    {
        $this->viewsDir = __DIR__ . '/../fixtures/views';
    }

    public function test_environment_is_built_with_config_options(): void
    {
        $config = new TemplateConfig(
            paths: ['__main__' => [$this->viewsDir]],
            debug: true,
            strictVariables: false,
            charset: 'ISO-8859-1',
            autoReload: true,
            autoescape: false,
        );

        $environment = $this->makeFactory($config)->environment();

        self::assertTrue($environment->isDebug());
        self::assertFalse($environment->isStrictVariables());
        self::assertSame('ISO-8859-1', $environment->getCharset());
        self::assertTrue($environment->isAutoReload());
    }

    public function test_namespaced_paths_are_registered(): void
    {
        $config = new TemplateConfig(paths: [
            '__main__' => [$this->viewsDir],
            'admin' => [$this->viewsDir],
        ]);

        $loader = $this->makeFactory($config)->environment()->getLoader();

        self::assertTrue($loader->exists('hello.twig'));
        self::assertTrue($loader->exists('@admin/hello.twig'));
    }

    public function test_null_cache_disables_caching(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);

        $environment = $this->makeFactory($config)->environment();

        self::assertFalse($environment->getCache());
    }

    public function test_debug_extension_registered_when_debug_true(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]], debug: true);

        $environment = $this->makeFactory($config)->environment();

        self::assertTrue($environment->hasExtension(DebugExtension::class));
    }

    public function test_debug_extension_not_registered_when_debug_false(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);

        $environment = $this->makeFactory($config)->environment();

        self::assertFalse($environment->hasExtension(DebugExtension::class));
    }

    public function test_extensions_are_discovered_from_manifest(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);
        $container = new ArrayContainer();
        $container->set(GreetingExtension::class, new GreetingExtension());

        $manifest = $this->makeManifest([GreetingExtension::class => 'singleton']);
        $factory = new EngineFactory($config, $manifest, $container);

        $environment = $factory->environment();

        self::assertTrue($environment->hasExtension(GreetingExtension::class));
    }

    public function test_non_extension_services_are_ignored(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);
        $manifest = $this->makeManifest([TemplateConfig::class => 'singleton']);
        $factory = new EngineFactory($config, $manifest, new ArrayContainer());

        $environment = $factory->environment();

        self::assertFalse($environment->hasExtension(TemplateConfig::class));
    }

    public function test_unknown_classes_are_skipped(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);
        $manifest = $this->makeManifest(['Acme\\DoesNotExist' => 'singleton']);
        $factory = new EngineFactory($config, $manifest, new ArrayContainer());

        $factory->environment();

        $this->addToAssertionCount(1);
    }

    public function test_environment_is_cached(): void
    {
        $factory = $this->makeFactory(new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]));

        self::assertSame($factory->environment(), $factory->environment());
    }

    private function makeFactory(TemplateConfig $config): EngineFactory
    {
        return new EngineFactory($config, $this->makeManifest([]), new ArrayContainer());
    }

    /**
     * @param array<string, string> $services
     */
    private function makeManifest(array $services): Manifest
    {
        $info = new PackageInfo(
            name: 'acme/test',
            description: '',
            url: '',
            author: '',
            services: $services,
            configs: [],
            bindings: [],
        );

        return new Manifest(['acme/test' => $info], '2026-04-22T00:00:00+00:00');
    }
}
