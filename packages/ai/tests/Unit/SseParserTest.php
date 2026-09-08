<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Transport\SseParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SseParserTest extends TestCase
{
    #[Test]
    public function oneChunkOneFrame(): void
    {
        $frames = (new SseParser())->push("event: message_start\ndata: {\"a\":1}\n\n");

        self::assertSame([['event' => 'message_start', 'data' => '{"a":1}']], $frames);
    }

    #[Test]
    public function splitChunksStillYieldWholeFrames(): void
    {
        $parser = new SseParser();

        self::assertSame([], $parser->push('data: {"a"'));
        self::assertSame([], $parser->push(':1}'));

        $frames = $parser->push("\n\n");

        self::assertSame([['event' => null, 'data' => '{"a":1}']], $frames);
    }

    #[Test]
    public function aTrailingPartialLineIsHeld(): void
    {
        $parser = new SseParser();

        $first = $parser->push("data: one\n\ndata: tw");

        self::assertSame([['event' => null, 'data' => 'one']], $first);
        self::assertSame([], $parser->push('o'));
        self::assertSame([['event' => null, 'data' => 'two']], $parser->push("\n\n"));
    }

    #[Test]
    public function crlfTerminatorsAndLineEndingsParse(): void
    {
        $frames = (new SseParser())->push("event: x\r\ndata: y\r\n\r\n");

        self::assertSame([['event' => 'x', 'data' => 'y']], $frames);
    }

    #[Test]
    public function mixedTerminatorsNeverMergeTwoFrames(): void
    {
        /*
         * An earlier \n\n must win over a later \r\n\r\n: searching one spelling
         * first would swallow the boundary and hand the consumer one corrupt blob.
         */
        $frames = (new SseParser())->push("data: one\n\ndata: two\r\n\r\n");

        self::assertSame(
            [['event' => null, 'data' => 'one'], ['event' => null, 'data' => 'two']],
            $frames,
        );
    }

    #[Test]
    public function commentsAreSkippedAndDataLinesJoin(): void
    {
        $frames = (new SseParser())->push(": keep-alive\ndata: a\ndata: b\n\n");

        self::assertSame([['event' => null, 'data' => "a\nb"]], $frames);
    }

    #[Test]
    public function aFrameWithoutDataYieldsNothing(): void
    {
        self::assertSame([], (new SseParser())->push("event: ping\n\n"));
    }
}
