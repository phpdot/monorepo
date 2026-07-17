<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Discovery;

use PHPdot\Attribute\Discovery\TokenDiscovery;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedController;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedEnum;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedInterface;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedService;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedTrait;
use PHPdot\Attribute\Tests\Fixtures\Classes\BaseController;
use PHPdot\Attribute\Tests\Fixtures\Classes\ChildController;
use PHPdot\Attribute\Tests\Fixtures\Classes\PlainClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TokenDiscoveryTest extends TestCase
{
    private TokenDiscovery $discovery;

    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->discovery = new TokenDiscovery();
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures/Classes';
    }

    #[Test]
    public function discoversClassesFromDirectory(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        self::assertContains(AnnotatedController::class, $classes);
        self::assertContains(AnnotatedService::class, $classes);
        self::assertContains(PlainClass::class, $classes);
    }

    #[Test]
    public function discoversInterfaces(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        self::assertContains(AnnotatedInterface::class, $classes);
    }

    #[Test]
    public function discoversEnums(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        self::assertContains(AnnotatedEnum::class, $classes);
    }

    #[Test]
    public function discoversTraits(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        self::assertContains(AnnotatedTrait::class, $classes);
    }

    #[Test]
    public function discoversChildAndBaseClasses(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        self::assertContains(ChildController::class, $classes);
        self::assertContains(BaseController::class, $classes);
    }

    #[Test]
    public function filtersByNamespace(): void
    {
        $classes = $this->discovery->discover(
            [$this->fixturesDir],
            namespaces: ['PHPdot\\Attribute\\Tests\\Fixtures\\Classes'],
        );

        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            self::assertStringStartsWith(
                'PHPdot\\Attribute\\Tests\\Fixtures\\Classes',
                $class,
            );
        }
    }

    #[Test]
    public function excludesPatterns(): void
    {
        $classes = $this->discovery->discover(
            [$this->fixturesDir],
            excludePatterns: ['*Plain*'],
        );

        self::assertNotContains(PlainClass::class, $classes);
    }

    #[Test]
    public function excludeMultiplePatterns(): void
    {
        $classes = $this->discovery->discover(
            [$this->fixturesDir],
            excludePatterns: ['*Plain*', '*Enum*'],
        );

        self::assertNotContains(PlainClass::class, $classes);
        self::assertNotContains(AnnotatedEnum::class, $classes);
    }

    #[Test]
    public function namespacePlusExcludeFiltersCombined(): void
    {
        $classes = $this->discovery->discover(
            [$this->fixturesDir],
            namespaces: ['PHPdot\\Attribute\\Tests\\Fixtures\\Classes'],
            excludePatterns: ['*Interface*'],
        );

        self::assertNotEmpty($classes);
        self::assertNotContains(AnnotatedInterface::class, $classes);

        foreach ($classes as $class) {
            self::assertStringStartsWith('PHPdot\\Attribute\\Tests\\Fixtures\\Classes', $class);
        }
    }

    #[Test]
    public function returnsEmptyForNonExistentDirectory(): void
    {
        $classes = $this->discovery->discover(['/non/existent/path']);

        self::assertEmpty($classes);
    }

    #[Test]
    public function discoversSortedAlphabetically(): void
    {
        $classes = $this->discovery->discover([$this->fixturesDir]);

        $sorted = $classes;
        sort($sorted);

        self::assertSame($sorted, $classes);
    }

    #[Test]
    public function discoversFromMultipleDirectories(): void
    {
        $classesDir = dirname(__DIR__, 2) . '/Fixtures/Classes';
        $attrsDir = dirname(__DIR__, 2) . '/Fixtures/Attributes';

        $classes = $this->discovery->discover([$classesDir, $attrsDir]);

        self::assertContains(AnnotatedController::class, $classes);
        self::assertContains('PHPdot\\Attribute\\Tests\\Fixtures\\Attributes\\Route', $classes);
    }

    #[Test]
    public function discoversFromSrcDirectory(): void
    {
        $srcDir = dirname(__DIR__, 3) . '/src';

        $classes = $this->discovery->discover(
            [$srcDir],
            namespaces: ['PHPdot\\Attribute\\'],
        );

        self::assertNotEmpty($classes);
        self::assertContains('PHPdot\\Attribute\\Scanner', $classes);
        self::assertContains('PHPdot\\Attribute\\Registry', $classes);
    }
}
