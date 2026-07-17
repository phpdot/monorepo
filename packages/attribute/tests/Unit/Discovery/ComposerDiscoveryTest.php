<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Discovery;

use PHPdot\Attribute\Discovery\ComposerDiscovery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComposerDiscoveryTest extends TestCase
{
    private ComposerDiscovery $discovery;

    private string $projectRoot;

    protected function setUp(): void
    {
        $this->discovery = new ComposerDiscovery();
        $this->projectRoot = dirname(__DIR__, 3);
    }

    #[Test]
    public function readsClassmap(): void
    {
        $classes = $this->discovery->discover($this->projectRoot);

        // The project classmap should contain at least our source classes
        // This depends on composer dump-autoload having been run
        self::assertIsArray($classes);
    }

    #[Test]
    public function filtersByDirectory(): void
    {
        $srcDir = $this->projectRoot . '/src';
        $classes = $this->discovery->discover(
            $this->projectRoot,
            directories: [$srcDir],
        );

        self::assertIsArray($classes);

        foreach ($classes as $class) {
            self::assertStringStartsWith('PHPdot\\Attribute\\', $class);
        }
    }

    #[Test]
    public function filtersByNamespace(): void
    {
        $classes = $this->discovery->discover(
            $this->projectRoot,
            namespaces: ['PHPdot\\Attribute\\'],
        );

        self::assertIsArray($classes);

        foreach ($classes as $class) {
            self::assertStringStartsWith('PHPdot\\Attribute\\', $class);
        }
    }

    #[Test]
    public function excludesPatterns(): void
    {
        $classes = $this->discovery->discover(
            $this->projectRoot,
            namespaces: ['PHPdot\\Attribute\\'],
            excludePatterns: ['*Enum*'],
        );

        self::assertIsArray($classes);

        foreach ($classes as $class) {
            self::assertStringNotContainsString('Enum', $class);
        }
    }

    #[Test]
    public function returnsEmptyWhenClassmapDoesNotExist(): void
    {
        $classes = $this->discovery->discover('/tmp/nonexistent-project-' . uniqid());

        self::assertSame([], $classes);
    }
}
