<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Bridge\ToolCallAssembler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolCallAssemblerTest extends TestCase
{
    #[Test]
    public function fragmentsAssembleIntoOneDecodedCall(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->open(0, 'call-1', 'catalog_search');
        $assembler->append(0, '{"que');
        $assembler->append(0, 'ry":"kettle"}');

        $call = $assembler->close(0);

        self::assertNotNull($call);
        self::assertSame('call-1', $call->id);
        self::assertSame('catalog_search', $call->name);
        self::assertSame(['query' => 'kettle'], $call->arguments);
    }

    #[Test]
    public function reopeningAnIndexNeverErasesIdentity(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->open(1, 'call-1', 'catalog_search');
        $assembler->open(1);
        $assembler->append(1, '{}');

        $call = $assembler->close(1);

        self::assertNotNull($call);
        self::assertSame('call-1', $call->id);
        self::assertSame('catalog_search', $call->name);
    }

    #[Test]
    public function appendAutoOpensItsIndex(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->append(0, '{}');

        self::assertNull($assembler->close(0), 'no identity ever arrived, so the call cannot be trusted');
    }

    #[Test]
    public function emptyArgumentsDecodeToAnEmptyArray(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->open(0, 'call-1', 'catalog_purge');

        $call = $assembler->close(0);

        self::assertNotNull($call);
        self::assertSame([], $call->arguments);
    }

    #[Test]
    public function invalidJsonIsDroppedNotGuessedAt(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->open(0, 'call-1', 'catalog_search');
        $assembler->append(0, '{"query":"kettle');

        self::assertNull($assembler->close(0));
    }

    #[Test]
    public function flushClosesEverythingInOpenedOrder(): void
    {
        $assembler = new ToolCallAssembler();

        $assembler->open(2, 'call-2', 'two');
        $assembler->open(0, 'call-0', 'zero');

        $calls = $assembler->flush();

        self::assertSame(['call-2', 'call-0'], [$calls[0]->id, $calls[1]->id]);
        self::assertTrue($assembler->isEmpty());
    }

    #[Test]
    public function closingAnUnknownIndexAnswersNull(): void
    {
        self::assertNull((new ToolCallAssembler())->close(7));
    }
}
