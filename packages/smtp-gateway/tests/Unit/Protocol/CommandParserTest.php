<?php

declare(strict_types=1);

namespace PHPdot\Mail\Gateway\Tests\Unit\Protocol;

use PHPdot\Mail\Gateway\DataType\Enum\SmtpCommand;
use PHPdot\Mail\Gateway\Exception\ProtocolException;
use PHPdot\Mail\Gateway\Protocol\CommandParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandParserTest extends TestCase
{
    private CommandParser $parser;

    protected function setUp(): void
    {
        $this->parser = new CommandParser();
    }

    #[Test]
    public function parsesEhlo(): void
    {
        $cmd = $this->parser->parse('EHLO example.com');
        self::assertSame(SmtpCommand::Ehlo, $cmd->verb);
        self::assertSame('example.com', $cmd->argument);
    }

    #[Test]
    public function parsesMailFrom(): void
    {
        $cmd = $this->parser->parse('MAIL FROM:<user@example.com> SIZE=1024');
        self::assertSame(SmtpCommand::MailFrom, $cmd->verb);
        self::assertStringContainsString('FROM:<user@example.com>', $cmd->argument);
    }

    #[Test]
    public function parsesQuitNoArgument(): void
    {
        $cmd = $this->parser->parse('QUIT');
        self::assertSame(SmtpCommand::Quit, $cmd->verb);
        self::assertSame('', $cmd->argument);
    }

    #[Test]
    public function caseInsensitive(): void
    {
        $cmd = $this->parser->parse('ehlo EXAMPLE.COM');
        self::assertSame(SmtpCommand::Ehlo, $cmd->verb);
    }

    #[Test]
    public function unknownCommandThrows(): void
    {
        $this->expectException(ProtocolException::class);
        $this->parser->parse('VRFY user@example.com');
    }

    #[Test]
    public function extractsAngleBracketAddress(): void
    {
        $addr = CommandParser::extractAddress('FROM:<user@example.com>');
        self::assertSame('user@example.com', $addr);
    }

    #[Test]
    public function extractsAddressWithSize(): void
    {
        $addr = CommandParser::extractAddress('FROM:<user@example.com> SIZE=1024 BODY=8BITMIME');
        self::assertSame('user@example.com', $addr);
    }

    #[Test]
    public function extractsEmptyAddress(): void
    {
        // Bounce address (empty sender)
        $addr = CommandParser::extractAddress('FROM:<>');
        self::assertSame('', $addr);
    }

    #[Test]
    public function extractsSize(): void
    {
        self::assertSame(1024, CommandParser::extractSize('FROM:<a@b.com> SIZE=1024'));
        self::assertNull(CommandParser::extractSize('FROM:<a@b.com>'));
    }

    #[Test]
    public function extractsBodyType(): void
    {
        self::assertSame('8BITMIME', CommandParser::extractBodyType('FROM:<a@b.com> BODY=8BITMIME'));
        self::assertNull(CommandParser::extractBodyType('FROM:<a@b.com>'));
    }

    #[Test]
    public function parsesAPathAndItsParameters(): void
    {
        self::assertSame(['a@b.test', 'SIZE=10'], CommandParser::parsePath('FROM: <a@b.test> SIZE=10', 'FROM'));
        self::assertSame(['', ''], CommandParser::parsePath('FROM:<>', 'FROM'));
        self::assertSame(['u@d.test', ''], CommandParser::parsePath('TO:<@r1,@r2:u@d.test>', 'TO'));
        self::assertSame(['Postmaster', ''], CommandParser::parsePath('TO:<Postmaster>', 'TO'));
    }

    #[Test]
    public function refusesAPathThatCouldCarryAnotherCommand(): void
    {
        self::assertNull(CommandParser::parsePath("TO:<bob@y.test\nRCPT TO:<x@evil.test>>", 'TO'));
        self::assertNull(CommandParser::parsePath("TO:<bob@y.test\0>", 'TO'));
        self::assertNull(CommandParser::parsePath('FROM:<a b c>', 'FROM'));
        self::assertNull(CommandParser::parsePath('TO:junk no brackets', 'TO'));
        self::assertNull(CommandParser::parsePath('TO:<nobody>', 'TO'));
        self::assertNull(CommandParser::parsePath('FROM:<a@b.test>SIZE=1', 'FROM'));
    }
}
