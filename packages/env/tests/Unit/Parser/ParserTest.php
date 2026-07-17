<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit\Parser;

use PHPdot\Env\Parser\Lexer;
use PHPdot\Env\Parser\Parser;
use PHPdot\Env\Parser\Resolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    #[Test]
    public function fullParseWithInterpolation(): void
    {
        $parser = new Parser(new Lexer(), new Resolver());

        $result = $parser->parse("BASE=/app\nDATA_DIR=\${BASE}/data\n");

        self::assertSame('/app', $result['BASE']);
        self::assertSame('/app/data', $result['DATA_DIR']);
    }

    #[Test]
    public function multiFileWithPredefinedValues(): void
    {
        $parser = new Parser(new Lexer(), new Resolver());

        $result = $parser->parse(
            "RESULT=\${EXTERNAL}/path\n",
            '',
            ['EXTERNAL' => '/pre'],
        );

        self::assertSame('/pre/path', $result['RESULT']);
    }

    #[Test]
    public function duplicateKeysLastWins(): void
    {
        $parser = new Parser(new Lexer(), new Resolver());

        $result = $parser->parse("DUP=first\nDUP=second\n");

        self::assertSame('second', $result['DUP']);
    }

    #[Test]
    public function staticCreateFactory(): void
    {
        $parser = Parser::create();

        $result = $parser->parse("FOO=bar\n");

        self::assertSame('bar', $result['FOO']);
    }
}
