<?php

declare(strict_types=1);

namespace PHPdot\Template\Tests\Integration;

use PHPdot\Package\Manifest;
use PHPdot\Package\PackageInfo;
use PHPdot\Template\EngineFactory;
use PHPdot\Template\TemplateConfig;
use PHPdot\Template\Tests\Stubs\ArrayContainer;
use PHPdot\Template\Tests\Stubs\GreetingExtension;
use PHPdot\Template\View;
use PHPUnit\Framework\TestCase;

final class EndToEndTest extends TestCase
{
    private string $viewsDir;

    private string $cacheDir;

    private string $uniqueViewsDir;

    protected function setUp(): void
    {
        $this->viewsDir = __DIR__ . '/../fixtures/views';
        $unique = bin2hex(random_bytes(8));
        $this->cacheDir = sys_get_temp_dir() . '/phpdot-template-cache-' . $unique;
        $this->uniqueViewsDir = sys_get_temp_dir() . '/phpdot-template-views-' . $unique;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->cacheDir)) {
            $this->removeDirectory($this->cacheDir);
        }

        if (is_dir($this->uniqueViewsDir)) {
            $this->removeDirectory($this->uniqueViewsDir);
        }
    }

    public function test_renders_with_discovered_extension(): void
    {
        $config = new TemplateConfig(paths: ['__main__' => [$this->viewsDir]]);

        $container = new ArrayContainer();
        $container->set(GreetingExtension::class, new GreetingExtension());

        $info = new PackageInfo(
            name: 'acme/greet',
            description: '',
            url: '',
            author: '',
            services: [GreetingExtension::class => 'singleton'],
            configs: [],
            bindings: [],
        );
        $manifest = new Manifest(['acme/greet' => $info], '2026-04-22T00:00:00+00:00');

        $view = new View(new EngineFactory($config, $manifest, $container));

        self::assertSame("hello, world\n", $view->render('greet.twig', ['name' => 'world']));
    }

    public function test_compiled_cache_is_written_and_reused(): void
    {
        mkdir($this->uniqueViewsDir, 0o755, true);
        $template = 'cache_' . bin2hex(random_bytes(4)) . '.twig';
        file_put_contents(
            $this->uniqueViewsDir . '/' . $template,
            "Cached {{ name }}\n",
        );

        $config = new TemplateConfig(
            paths: ['__main__' => [$this->uniqueViewsDir]],
            cache: $this->cacheDir,
        );

        $view = $this->makeView($config);

        self::assertSame("Cached first\n", $view->render($template, ['name' => 'first']));
        self::assertTrue(is_dir($this->cacheDir), 'Cache directory should be created.');
        self::assertNotSame([], $this->collectFiles($this->cacheDir), 'Cache directory should contain compiled files.');

        $warm = $this->makeView($config);
        self::assertSame("Cached second\n", $warm->render($template, ['name' => 'second']));
    }

    private function makeView(TemplateConfig $config): View
    {
        $manifest = new Manifest([], '2026-04-22T00:00:00+00:00');

        return new View(new EngineFactory($config, $manifest, new ArrayContainer()));
    }

    /**
     * @return list<string>
     */
    private function collectFiles(string $directory): array
    {
        $results = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile()) {
                $results[] = $file->getPathname();
            }
        }

        return $results;
    }

    private function removeDirectory(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
