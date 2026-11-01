<?php

declare(strict_types=1);

/**
 * The stream spool keeps the bytes it is given with CRLF line endings, finds across its read windows, and stops at its limit.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Unit\Source;

use PHPdot\Mail\Parser\Source\StreamSource;
use PHPdot\Mail\Parser\Source\StringSource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamSourceTest extends TestCase
{
    #[Test]
    public function bareLineFeedsBecomeCrlfEvenWhenACarriageReturnEndsARead(): void
    {
        $raw = str_repeat('a', 65_535) . "\r\nb\nc\rd\r";
        $source = self::spooled($raw, 0);

        self::assertSame(str_repeat('a', 65_535) . "\r\nb\r\nc\rd\r", $source->read(0, $source->length()));
        self::assertFalse($source->truncated);
    }

    #[Test]
    public function aNeedleIsFoundAcrossTheEdgeOfARead(): void
    {
        $raw = str_repeat('x', 65_530) . '--boundary' . str_repeat('y', 100);
        $source = self::spooled($raw, 0);

        self::assertSame(65_530, $source->find('--boundary', 0, $source->length()));
        self::assertNull($source->find('--boundary', 65_531, $source->length()));
        self::assertNull($source->find('--boundary', 0, 65_539));
    }

    #[Test]
    public function readsAgreeWithTheSameBytesHeldInMemory(): void
    {
        $raw = random_bytes(200_000);
        $raw = str_replace(["\r", "\n"], '', $raw);
        $stream = self::spooled($raw, 0);
        $memory = new StringSource($raw);

        foreach ([[0, 10], [65_530, 20], [131_000, 70_000], [199_990, 50]] as [$offset, $length]) {
            self::assertSame($memory->read($offset, $length), $stream->read($offset, $length));
        }
    }

    #[Test]
    public function aLimitKeepsThatManyBytesAndSaysMoreWereLeft(): void
    {
        $exact = self::spooled(str_repeat('a', 100), 100);
        $over = self::spooled(str_repeat('a', 101), 100);

        self::assertSame(100, $exact->length());
        self::assertFalse($exact->truncated);
        self::assertSame(100, $over->length());
        self::assertTrue($over->truncated);
    }

    private static function spooled(string $raw, int $limit): StreamSource
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, $raw);
        rewind($stream);

        return StreamSource::spool($stream, $limit);
    }

    #[Test]
    public function aNeedleLongerThanAReadIsStillFound(): void
    {
        $needle = str_repeat('Q', 70_000);
        $source = self::spooled(str_repeat('x', 100_000) . $needle . 'tail', 0);

        self::assertSame(100_000, $source->find($needle, 0, $source->length()));
    }

    #[Test]
    public function manySearchesInsideOneWindowReadItOnce(): void
    {
        $source = self::spooled(str_repeat("a --b\r\n", 600_000), 0);
        $started = hrtime(true);

        for ($at = 0, $hits = 0; ($found = $source->find('--b', $at, $source->length())) !== null; $at = $found + 1, $hits++);

        self::assertSame(600_000, $hits);
        self::assertLessThan(3.0, (hrtime(true) - $started) / 1e9);
    }
}
