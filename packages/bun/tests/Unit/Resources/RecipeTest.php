<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Resources;

use PHPdot\Bun\Resources\Recipe;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecipeTest extends TestCase
{
    #[Test]
    public function declaresEntrypointsInOrderAcrossCalls(): void
    {
        $recipe = new Recipe('/srv/app/resources/.bun');
        $recipe->entries('/srv/app/resources/platform.ts');
        $recipe->entries('/srv/app/resources/apps/a.ts', '/srv/app/resources/apps/b.ts');

        self::assertSame(
            ['/srv/app/resources/platform.ts', '/srv/app/resources/apps/a.ts', '/srv/app/resources/apps/b.ts'],
            $recipe->entryPoints(),
        );
    }

    #[Test]
    public function declaresToolStepsInOrderWithTheirWatchShape(): void
    {
        $recipe = new Recipe('/srv/app/resources/.bun');
        $recipe->bunx('a', ['-i', 'in']);
        $recipe->bunx('b', ['-i', 'in'], watch: ['--watch']);
        $recipe->bunxWatch('c', ['serve']);

        self::assertSame(
            [
                ['kind' => 'bunx', 'command' => 'a', 'args' => ['-i', 'in'], 'watchArgs' => null],
                ['kind' => 'bunx', 'command' => 'b', 'args' => ['-i', 'in'], 'watchArgs' => ['--watch']],
                ['kind' => 'bunx', 'command' => 'c', 'args' => [], 'watchArgs' => ['serve']],
            ],
            $recipe->steps(),
        );
    }

    #[Test]
    public function declaresScriptStepsAlongsideToolStepsInOrder(): void
    {
        $home = '/srv/app/resources/.bun';
        $recipe = new Recipe($home);
        $recipe->run($home . '/build/extract-icons.mjs', ['-i', 'svg']);
        $recipe->bunx('a', ['-i', 'in']);
        $recipe->runWatch($home . '/build/watch.mjs', ['--watch']);

        self::assertSame(
            [
                ['kind' => 'run', 'command' => $home . '/build/extract-icons.mjs', 'args' => ['-i', 'svg'], 'watchArgs' => null],
                ['kind' => 'bunx', 'command' => 'a', 'args' => ['-i', 'in'], 'watchArgs' => null],
                ['kind' => 'run', 'command' => $home . '/build/watch.mjs', 'args' => [], 'watchArgs' => ['--watch']],
            ],
            $recipe->steps(),
        );
    }

    #[Test]
    public function aBridgeIsWrittenUnderHomeBuildAndItsPathReturned(): void
    {
        $home = sys_get_temp_dir() . '/phpdot-bun-recipe-' . bin2hex(random_bytes(4));
        $recipe = new Recipe($home);

        $path = $recipe->bridge('app.css', "@import \"some-tool\";\n");

        self::assertSame($home . '/build/app.css', $path);
        self::assertSame("@import \"some-tool\";\n", (string) file_get_contents($path));
        self::assertSame($home . '/build', $recipe->buildDir());

        unlink($path);
        rmdir($home . '/build');
        rmdir($home);
    }
}
