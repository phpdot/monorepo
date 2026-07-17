<?php

declare(strict_types=1);

namespace PHPdot\Bun\Tests\Unit\Build;

use PHPdot\Bun\Build\BuildSpec;
use PHPUnit\Framework\TestCase;

final class BuildSpecTest extends TestCase
{
    public function testEmptySpecProducesNoFlags(): void
    {
        self::assertSame([], (new BuildSpec())->toOptions()->toArguments());
    }

    public function testWithersMapToFlags(): void
    {
        $args = (new BuildSpec())
            ->outDir('public/build')
            ->target('browser')
            ->minify()
            ->splitting()
            ->hashedNames()
            ->sourcemap('linked')
            ->metafile('public/build/meta.json')
            ->define('A=1')
            ->external('react')
            ->toOptions()
            ->toArguments();

        self::assertContains('--outdir=public/build', $args);
        self::assertContains('--target=browser', $args);
        self::assertContains('--minify', $args);
        self::assertContains('--sourcemap=linked', $args);
        self::assertContains('--metafile=public/build/meta.json', $args);
        self::assertContains('--define=A=1', $args);
    }

    public function testWithersAreImmutable(): void
    {
        $base = (new BuildSpec())->minify();
        $derived = $base->noMinify();

        self::assertNotSame($base, $derived);
        self::assertContains('--minify', $base->toOptions()->toArguments());
        self::assertNotContains('--minify', $derived->toOptions()->toArguments());
    }

    public function testRepeatableWithersAppend(): void
    {
        $args = (new BuildSpec())
            ->define('A=1')
            ->define('B=2')
            ->external('react')
            ->drop('console')
            ->toOptions()
            ->toArguments();

        self::assertSame(['--define=A=1', '--define=B=2', '--external=react', '--drop=console'], $args);
    }
}
