<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Protocol;

use PHPdot\Mail\Gateway\Protocol\LineParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LineParserTest extends TestCase
{
    private LineParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LineParser();
    }

    #[Test]
    public function parsesSingleLine(): void
    {
        $lines = $this->parser->feed("EHLO example.com\r\n");
        self::assertSame(['EHLO example.com'], $lines);
    }

    #[Test]
    public function parsesMultipleLines(): void
    {
        $lines = $this->parser->feed("MAIL FROM:<a@b.com>\r\nRCPT TO:<c@d.com>\r\n");
        self::assertCount(2, $lines);
        self::assertSame('MAIL FROM:<a@b.com>', $lines[0]);
        self::assertSame('RCPT TO:<c@d.com>', $lines[1]);
    }

    #[Test]
    public function handlesPartialReads(): void
    {
        $lines = $this->parser->feed("EHLO exam");
        self::assertSame([], $lines);

        $lines = $this->parser->feed("ple.com\r\n");
        self::assertSame(['EHLO example.com'], $lines);
    }

    #[Test]
    public function handlesBareLineFeed(): void
    {
        $lines = $this->parser->feed("NOOP\n");
        self::assertSame(['NOOP'], $lines);
    }

    #[Test]
    public function dataModeParsesLongLines(): void
    {
        $longLine = str_repeat('A', 800);
        $lines = $this->parser->feedData($longLine . "\r\n");
        self::assertSame([$longLine], $lines);
    }

    #[Test]
    public function pipelining(): void
    {
        $pipelined = "MAIL FROM:<a@b.com>\r\nRCPT TO:<c@d.com>\r\nRCPT TO:<e@f.com>\r\nDATA\r\n";
        $lines = $this->parser->feed($pipelined);
        self::assertCount(4, $lines);
        self::assertSame('DATA', $lines[3]);
    }

    #[Test]
    public function feedDataBufferLimitPreventsMemoryExhaustion(): void
    {
        $parser = new LineParser(maxLineLength: 512, maxBufferSize: 1024);

        // Send data exceeding buffer limit without any CRLF
        $lines = $parser->feedData(str_repeat('X', 2000));
        self::assertCount(1, $lines);
        self::assertSame(1024, strlen($lines[0]));

        // Buffer should be cleared after flush
        self::assertSame('', $parser->pending());
    }

    #[Test]
    public function resetClearsBuffer(): void
    {
        $this->parser->feed("partial");
        self::assertSame('partial', $this->parser->pending());

        $this->parser->reset();
        self::assertSame('', $this->parser->pending());
    }
}
