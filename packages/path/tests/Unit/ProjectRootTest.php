<?php

declare(strict_types=1);

namespace PHPdot\Path\Tests\Unit;

use PHPdot\Path\Exception\ProjectRootNotFound;
use PHPdot\Path\ProjectRoot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProjectRootTest extends TestCase
{
    #[Test]
    public function discover_resolves_the_composer_root_from_runtime_metadata(): void
    {
        $root = ProjectRoot::discover();

        // During this test run, Composer's root package is phpdot/path itself.
        self::assertDirectoryExists($root->path);
        self::assertFileExists($root->path . '/composer.json');
    }

    #[Test]
    public function from_path_accepts_a_real_directory(): void
    {
        $root = ProjectRoot::fromPath(__DIR__);

        self::assertSame(realpath(__DIR__), $root->path);
    }

    #[Test]
    public function from_path_rejects_a_non_directory(): void
    {
        $this->expectException(ProjectRootNotFound::class);

        ProjectRoot::fromPath(__DIR__ . '/does-not-exist');
    }
}
