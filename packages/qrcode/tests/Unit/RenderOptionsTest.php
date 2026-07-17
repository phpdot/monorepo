<?php

declare(strict_types=1);

namespace PHPdot\QrCode\Tests\Unit;

use InvalidArgumentException;
use PHPdot\QrCode\Color;
use PHPdot\QrCode\RenderOptions;
use PHPUnit\Framework\TestCase;

final class RenderOptionsTest extends TestCase
{
    public function test_defaults(): void
    {
        $options = new RenderOptions();

        self::assertSame(300, $options->size);
        self::assertSame(4, $options->margin);
        self::assertSame('#000000', $options->foreground->toHex());
        self::assertSame('#ffffff', $options->background->toHex());
    }

    public function test_mutators_return_new_instances(): void
    {
        $base = new RenderOptions();
        $changed = $base
            ->withSize(120)
            ->withMargin(2)
            ->withForeground(Color::fromHex('#123456'))
            ->withBackground(Color::transparent());

        // Original is untouched.
        self::assertSame(300, $base->size);
        self::assertSame(4, $base->margin);

        self::assertSame(120, $changed->size);
        self::assertSame(2, $changed->margin);
        self::assertSame('#123456', $changed->foreground->toHex());
        self::assertTrue($changed->background->isTransparent());
        self::assertNotSame($base, $changed);
    }

    public function test_zero_size_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenderOptions(size: 0);
    }

    public function test_negative_margin_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RenderOptions(margin: -1);
    }
}
