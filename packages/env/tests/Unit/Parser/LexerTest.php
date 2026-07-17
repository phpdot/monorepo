<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit\Parser;

use PHPdot\Env\Exception\EncodingException;
use PHPdot\Env\Exception\ParseException;
use PHPdot\Env\Parser\Lexer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    #[Test]
    public function basicKeyValuePairs(): void
    {
        $entries = $this->lexer->tokenize("APP_NAME=TestApp\nAPP_PORT=8080\n");

        self::assertCount(2, $entries);
        self::assertSame('APP_NAME', $entries[0]->key);
        self::assertSame('TestApp', $entries[0]->value);
        self::assertSame('APP_PORT', $entries[1]->key);
        self::assertSame('8080', $entries[1]->value);
    }

    #[Test]
    public function doubleQuotedValues(): void
    {
        $entries = $this->lexer->tokenize('DB_HOST="localhost"' . "\n");

        self::assertCount(1, $entries);
        self::assertSame('localhost', $entries[0]->value);
        self::assertTrue($entries[0]->interpolate);
    }

    #[Test]
    public function singleQuotedValues(): void
    {
        $entries = $this->lexer->tokenize("DB_NAME='my_database'\n");

        self::assertCount(1, $entries);
        self::assertSame('my_database', $entries[0]->value);
        self::assertFalse($entries[0]->interpolate);
    }

    #[Test]
    public function emptyValueBare(): void
    {
        $entries = $this->lexer->tokenize("EMPTY_VAL=\n");

        self::assertCount(1, $entries);
        self::assertSame('', $entries[0]->value);
    }

    #[Test]
    public function emptyValueDoubleQuoted(): void
    {
        $entries = $this->lexer->tokenize('EMPTY_QUOTED=""' . "\n");

        self::assertCount(1, $entries);
        self::assertSame('', $entries[0]->value);
    }

    #[Test]
    public function emptyValueSingleQuoted(): void
    {
        $entries = $this->lexer->tokenize("EMPTY_SINGLE=''\n");

        self::assertCount(1, $entries);
        self::assertSame('', $entries[0]->value);
    }

    #[Test]
    public function inlineCommentStripped(): void
    {
        $entries = $this->lexer->tokenize("INLINE_COMMENT=value # this is a comment\n");

        self::assertCount(1, $entries);
        self::assertSame('value', $entries[0]->value);
    }

    #[Test]
    public function bareHashNotStripped(): void
    {
        $entries = $this->lexer->tokenize("BARE_HASH=color#fff\n");

        self::assertCount(1, $entries);
        self::assertSame('color#fff', $entries[0]->value);
    }

    #[Test]
    public function hashInQuotesNotStripped(): void
    {
        $entries = $this->lexer->tokenize('QUOTED_HASH="value # not a comment"' . "\n");

        self::assertCount(1, $entries);
        self::assertSame('value # not a comment', $entries[0]->value);
    }

    #[Test]
    public function exportPrefixStripped(): void
    {
        $entries = $this->lexer->tokenize("export FOO=bar\n");

        self::assertCount(1, $entries);
        self::assertSame('FOO', $entries[0]->key);
        self::assertSame('bar', $entries[0]->value);
    }

    #[Test]
    public function multilineDoubleQuoted(): void
    {
        $content = "RSA_KEY=\"-----BEGIN RSA KEY-----\nMIIBogIBAAJBALRiMLAH\n-----END RSA KEY-----\"\n";
        $entries = $this->lexer->tokenize($content);

        self::assertCount(1, $entries);
        self::assertSame("-----BEGIN RSA KEY-----\nMIIBogIBAAJBALRiMLAH\n-----END RSA KEY-----", $entries[0]->value);
    }

    #[Test]
    public function multilineSingleQuoted(): void
    {
        $content = "SINGLE_MULTI='line one\nline two\nline three'\n";
        $entries = $this->lexer->tokenize($content);

        self::assertCount(1, $entries);
        self::assertSame("line one\nline two\nline three", $entries[0]->value);
        self::assertFalse($entries[0]->interpolate);
    }

    #[Test]
    #[DataProvider('escapeSequenceProvider')]
    public function escapeSequencesInDoubleQuotes(string $input, string $expected): void
    {
        $entries = $this->lexer->tokenize($input);

        self::assertCount(1, $entries);
        self::assertSame($expected, $entries[0]->value);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function escapeSequenceProvider(): array
    {
        return [
            'newline' => ['NEWLINE="hello\nworld"' . "\n", "hello\nworld"],
            'tab' => ['TAB="col1\tcol2"' . "\n", "col1\tcol2"],
            'backslash' => ['BACKSLASH="back\\\\slash"' . "\n", 'back\\slash'],
            'escaped quote' => ['ESCAPED_QUOTE="say\\"hi\\""' . "\n", 'say"hi"'],
            'escaped dollar' => ['ESCAPED_DOLLAR="cost\\$5"' . "\n", 'cost$5'],
        ];
    }

    #[Test]
    public function noEscapesInSingleQuotes(): void
    {
        $entries = $this->lexer->tokenize("SINGLE_NO_ESCAPE='hello\\nworld'\n");

        self::assertCount(1, $entries);
        self::assertSame('hello\\nworld', $entries[0]->value);
    }

    #[Test]
    public function whitespaceAroundEquals(): void
    {
        $entries = $this->lexer->tokenize("SPACES_AROUND = value\n");

        self::assertCount(1, $entries);
        self::assertSame('SPACES_AROUND', $entries[0]->key);
        self::assertSame('value', $entries[0]->value);
    }

    #[Test]
    public function skipsCommentLines(): void
    {
        $entries = $this->lexer->tokenize("# This is a comment\nFOO=bar\n");

        self::assertCount(1, $entries);
        self::assertSame('FOO', $entries[0]->key);
    }

    #[Test]
    public function skipsEmptyLines(): void
    {
        $entries = $this->lexer->tokenize("\n\nFOO=bar\n\n");

        self::assertCount(1, $entries);
        self::assertSame('FOO', $entries[0]->key);
    }

    #[Test]
    public function bomDetectionThrowsEncodingException(): void
    {
        $this->expectException(EncodingException::class);
        $this->expectExceptionMessage('UTF-8 BOM detected');

        $this->lexer->tokenize("\xEF\xBB\xBFFOO=bar\n");
    }

    #[Test]
    public function unclosedDoubleQuoteThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unclosed double quote');

        $this->lexer->tokenize('FOO="unclosed' . "\n");
    }

    #[Test]
    public function unclosedSingleQuoteThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Unclosed single quote');

        $this->lexer->tokenize("FOO='unclosed\n");
    }

    #[Test]
    public function keyFormatValidation(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected variable name');

        $this->lexer->tokenize("123INVALID=value\n");
    }

    #[Test]
    public function leadingUnderscoreKey(): void
    {
        $entries = $this->lexer->tokenize("_LEADING_UNDERSCORE=works\n");

        self::assertCount(1, $entries);
        self::assertSame('_LEADING_UNDERSCORE', $entries[0]->key);
        self::assertSame('works', $entries[0]->value);
    }

    #[Test]
    public function emptyContentReturnsNoEntries(): void
    {
        $entries = $this->lexer->tokenize('');

        self::assertCount(0, $entries);
    }

    #[Test]
    public function entryLineNumberIsCorrect(): void
    {
        $entries = $this->lexer->tokenize("# comment\n\nFOO=bar\n");

        self::assertCount(1, $entries);
        self::assertSame(3, $entries[0]->line);
    }

    #[Test]
    public function valueAtEndOfFileWithoutNewline(): void
    {
        $entries = $this->lexer->tokenize('FOO=bar');

        self::assertCount(1, $entries);
        self::assertSame('bar', $entries[0]->value);
    }
}
