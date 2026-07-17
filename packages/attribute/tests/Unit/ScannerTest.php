<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit;

use PHPdot\Attribute\Cache\FileCache;
use PHPdot\Attribute\Registry;
use PHPdot\Attribute\Scanner;
use PHPdot\Attribute\Tests\Fixtures\Attributes\Route;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedController;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedService;
use PHPdot\Attribute\Tests\Fixtures\Classes\PlainClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ScannerTest extends TestCase
{
    private string $cachePath;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/phpdot-attr-manager-test-' . uniqid() . '.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            unlink($this->cachePath);
        }
    }

    // --- scanClasses ---

    #[Test]
    public function scanClassesScansSpecificClasses(): void
    {
        $manager = new Scanner();

        $manager->scanClasses([
            AnnotatedController::class,
            AnnotatedService::class,
        ]);

        $registry = $manager->registry();

        self::assertInstanceOf(Registry::class, $registry);
        self::assertNotNull($registry->findByClass(AnnotatedController::class));
        self::assertNotNull($registry->findByClass(AnnotatedService::class));
    }

    #[Test]
    public function scanClassesReturnsRegistry(): void
    {
        $manager = new Scanner();
        $result = $manager->scanClasses([AnnotatedController::class]);

        self::assertInstanceOf(Registry::class, $result);
    }

    #[Test]
    public function scanClassesWithFilterOnlyScansMatchingAttributes(): void
    {
        $manager = new Scanner();
        $manager->scanClasses(
            [AnnotatedController::class],
            filter: [Route::class],
        );

        $registry = $manager->registry();
        $class = $registry->findByClass(AnnotatedController::class);
        self::assertNotNull($class);

        foreach ($class->all() as $result) {
            self::assertSame(Route::class, $result->attribute);
        }
    }

    #[Test]
    public function scanClassesWithEmptyListCreatesEmptyRegistry(): void
    {
        $manager = new Scanner();
        $manager->scanClasses([]);

        self::assertSame(0, $manager->registry()->count());
    }

    // --- registry ---

    #[Test]
    public function registryReturnsRegistryInstance(): void
    {
        $manager = new Scanner();
        $manager->scanClasses([AnnotatedController::class]);

        self::assertInstanceOf(Registry::class, $manager->registry());
    }

    #[Test]
    public function registryThrowsWhenNotScanned(): void
    {
        $manager = new Scanner();

        $this->expectException(RuntimeException::class);
        $manager->registry();
    }

    // --- scan with directories ---

    #[Test]
    public function scanWithDirectoriesDiscoversAndScans(): void
    {
        $fixturesDir = dirname(__DIR__) . '/Fixtures/Classes';
        $manager = new Scanner();

        $manager->scan([$fixturesDir]);

        $registry = $manager->registry();
        self::assertNotNull($registry->findByClass(AnnotatedController::class));
        self::assertNotNull($registry->findByClass(AnnotatedService::class));
        self::assertNotNull($registry->findByClass(PlainClass::class));
    }

    #[Test]
    public function scanWithFilterAndDirectories(): void
    {
        $fixturesDir = dirname(__DIR__) . '/Fixtures/Classes';
        $manager = new Scanner();

        $manager->scan([$fixturesDir], filter: [Route::class]);

        $registry = $manager->registry();
        $all = $registry->all();

        foreach ($all as $result) {
            self::assertSame(Route::class, $result->attribute);
        }
    }

    // --- Cache ---

    #[Test]
    public function withCacheWritesOnFirstScan(): void
    {
        $cache = new FileCache($this->cachePath);
        $manager = new Scanner(cache: $cache);

        $manager->scanClasses([AnnotatedController::class]);

        self::assertTrue($cache->has());
    }

    #[Test]
    public function withCacheReadsFromCacheOnSecondCall(): void
    {
        $cache = new FileCache($this->cachePath);
        $manager = new Scanner(cache: $cache);
        $manager->scanClasses([AnnotatedController::class]);

        $manager2 = new Scanner(cache: $cache);
        $manager2->scanClasses([AnnotatedController::class]);

        $registry = $manager2->registry();
        self::assertNotNull($registry->findByClass(AnnotatedController::class));
    }

    #[Test]
    public function clearCacheDeletesFile(): void
    {
        $cache = new FileCache($this->cachePath);
        $manager = new Scanner(cache: $cache);
        $manager->scanClasses([AnnotatedController::class]);
        self::assertTrue($cache->has());

        $manager->clearCache();

        self::assertFalse($cache->has());
    }

    #[Test]
    public function clearCacheResetsRegistry(): void
    {
        $cache = new FileCache($this->cachePath);
        $manager = new Scanner(cache: $cache);
        $manager->scanClasses([AnnotatedController::class]);

        $manager->clearCache();

        $this->expectException(RuntimeException::class);
        $manager->registry();
    }

    #[Test]
    public function forceRescanIgnoresCache(): void
    {
        $cache = new FileCache($this->cachePath);
        $manager = new Scanner(cache: $cache);

        $manager->scanClasses([AnnotatedController::class]);

        $manager->scanClasses(
            [AnnotatedController::class, AnnotatedService::class],
            forceRescan: true,
        );

        $registry = $manager->registry();
        self::assertNotNull($registry->findByClass(AnnotatedController::class));
        self::assertNotNull($registry->findByClass(AnnotatedService::class));
    }

    // --- scan() cache ---

    #[Test]
    public function scanChecksCacheBeforeDiscovery(): void
    {
        $fixturesDir = dirname(__DIR__) . '/Fixtures/Classes';
        $cache = new FileCache($this->cachePath);
        $scanner = new Scanner(cache: $cache);

        $scanner->scan([$fixturesDir]);
        self::assertTrue($cache->has());

        $scanner2 = new Scanner(cache: $cache);
        $scanner2->scan([$fixturesDir]);

        $registry = $scanner2->registry();
        self::assertNotNull($registry->findByClass(AnnotatedController::class));
    }

    #[Test]
    public function scanForceRescanIgnoresCache(): void
    {
        $fixturesDir = dirname(__DIR__) . '/Fixtures/Classes';
        $cache = new FileCache($this->cachePath);
        $scanner = new Scanner(cache: $cache);

        $scanner->scan([$fixturesDir]);

        $scanner->scan([$fixturesDir], forceRescan: true);

        self::assertNotNull($scanner->registry()->findByClass(AnnotatedController::class));
    }

    // --- scan() projectRoot ---

    #[Test]
    public function scanPassesProjectRootToDiscovery(): void
    {
        $fixturesDir = dirname(__DIR__) . '/Fixtures/Classes';
        $projectRoot = dirname(__DIR__, 2);
        $scanner = new Scanner();

        $scanner->scan([$fixturesDir], projectRoot: $projectRoot);

        self::assertNotNull($scanner->registry()->findByClass(AnnotatedController::class));
    }

    // --- No cache ---

    #[Test]
    public function worksWithoutCache(): void
    {
        $manager = new Scanner();
        $manager->scanClasses([AnnotatedController::class]);

        self::assertNotNull($manager->registry()->findByClass(AnnotatedController::class));
    }
}
