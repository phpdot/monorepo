<?php

declare(strict_types=1);

/**
 * The in-memory source finds within the stretch it is asked about, across the edges of its search window.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Unit\Source;

use PHPdot\Mail\Parser\Source\StringSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringSourceTest extends TestCase
{
    #[Test]
    public function aNeedleIsFoundAcrossTheEdgeOfTheWindow(): void
    {
        $source = new StringSource(str_repeat('x', 65_530) . '--boundary' . str_repeat('y', 100));

        self::assertSame(65_530, $source->find('--boundary', 0, $source->length()));
        self::assertNull($source->find('--boundary', 65_531, $source->length()));
        self::assertNull($source->find('--boundary', 0, 65_539));
        self::assertSame(65_530, $source->find('--boundary', 65_530, 65_540));
    }

    #[Test]
    public function searchesInAnyOrderAgreeWithAPlainSearch(): void
    {
        $bytes = str_repeat(str_repeat('a', 9_000) . '--b', 40);
        $source = new StringSource($bytes, false);

        foreach ([0, 300_000, 9_001, 120_000, 9_000, 359_000, 1, 200_003] as $from) {
            $expected = strpos($bytes, '--b', $from);

            self::assertSame($expected === false ? null : $expected, $source->find('--b', $from, strlen($bytes)));
            self::assertNull($source->find('--b', $from, $expected === false ? strlen($bytes) : $expected + 2));
        }
    }
}
