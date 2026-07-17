<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Discovery;

use PHPdot\Attribute\Discovery\ClassDiscovery;
use PHPdot\Attribute\Discovery\ComposerDiscovery;
use PHPdot\Attribute\Discovery\TokenDiscovery;
use PHPdot\Attribute\Tests\Fixtures\Classes\AnnotatedController;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClassDiscoveryTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = dirname(__DIR__, 2) . '/Fixtures/Classes';
    }

    #[Test]
    public function discoversWithTokenDiscoveryOnly(): void
    {
        $discovery = new ClassDiscovery(tokenDiscovery: new TokenDiscovery());

        $classes = $discovery->discover(directories: [$this->fixturesDir]);

        self::assertNotEmpty($classes);
        self::assertContains(AnnotatedController::class, $classes);
    }

    #[Test]
    public function discoversWithComposerDiscoveryWhenProjectRootProvided(): void
    {
        $projectRoot = dirname(__DIR__, 3);
        $discovery = new ClassDiscovery(
            composerDiscovery: new ComposerDiscovery(),
            tokenDiscovery: new TokenDiscovery(),
        );

        $classes = $discovery->discover(
            directories: [$this->fixturesDir],
            projectRoot: $projectRoot,
        );

        self::assertNotEmpty($classes);
    }

    #[Test]
    public function fallsBackToTokenWhenComposerReturnsEmpty(): void
    {
        $discovery = new ClassDiscovery(
            composerDiscovery: new ComposerDiscovery(),
            tokenDiscovery: new TokenDiscovery(),
        );

        // Use nonexistent project root so composer returns empty, token discovers via directory
        $classes = $discovery->discover(
            directories: [$this->fixturesDir],
            projectRoot: '/tmp/nonexistent-' . uniqid(),
        );

        self::assertNotEmpty($classes);
        self::assertContains(AnnotatedController::class, $classes);
    }

    #[Test]
    public function skipsComposerWhenProjectRootEmpty(): void
    {
        $discovery = new ClassDiscovery(
            composerDiscovery: new ComposerDiscovery(),
            tokenDiscovery: new TokenDiscovery(),
        );

        $classes = $discovery->discover(
            directories: [$this->fixturesDir],
            projectRoot: '',
        );

        self::assertNotEmpty($classes);
        self::assertContains(AnnotatedController::class, $classes);
    }

    #[Test]
    public function returnsEmptyWhenBothDiscoveriesNull(): void
    {
        $discovery = new ClassDiscovery();

        $classes = $discovery->discover(directories: [$this->fixturesDir]);

        self::assertSame([], $classes);
    }

    #[Test]
    public function returnsEmptyWhenComposerNullAndTokenNull(): void
    {
        $discovery = new ClassDiscovery(composerDiscovery: null, tokenDiscovery: null);

        $classes = $discovery->discover(
            directories: [$this->fixturesDir],
            projectRoot: dirname(__DIR__, 3),
        );

        self::assertSame([], $classes);
    }

    #[Test]
    public function passesNamespacesAndExcludePatternsThrough(): void
    {
        $discovery = new ClassDiscovery(tokenDiscovery: new TokenDiscovery());

        $classes = $discovery->discover(
            directories: [$this->fixturesDir],
            namespaces: ['PHPdot\\Attribute\\Tests\\Fixtures\\Classes'],
            excludePatterns: ['*Plain*'],
        );

        self::assertNotEmpty($classes);

        foreach ($classes as $class) {
            self::assertStringStartsWith('PHPdot\\Attribute\\Tests\\Fixtures\\Classes', $class);
            self::assertStringNotContainsString('Plain', $class);
        }
    }
}
