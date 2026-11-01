<?php

declare(strict_types=1);

/**
 * Hostile messages parse within bounded time and memory and yield the same parts every reader of them would see.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Security;

use PHPdot\Mail\Parser\Content\ContentStorage;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HostileMailTest extends TestCase
{
    #[Test]
    public function nestedMessageOverItsLimitsStaysAnAttachment(): void
    {
        $nested = 'Subject: ' . str_repeat('A', 200) . "\r\n\r\ninner";
        $raw = "Subject: outer\r\nContent-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\nhello\r\n"
            . "--b\r\nContent-Type: message/rfc822\r\n\r\n{$nested}\r\n--b--\r\n";

        $email = MailParser::parse($raw, new ParserConfig(maxHeaderSize: 150));

        self::assertSame('outer', $email->subject);
        self::assertSame('hello', $email->text);
        self::assertCount(1, $email->attachments);
        self::assertSame('message/rfc822', $email->attachments->all()[0]->mimeType);

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, new ParserConfig(strict: true, maxHeaderSize: 150));
    }

    #[Test]
    public function deepMultipartCostsAboutItsOwnSize(): void
    {
        $payload = str_repeat(str_repeat('A', 76) . "\r\n", 27_000);
        $part = "Content-Type: application/octet-stream\r\n\r\n{$payload}";

        for ($level = 0; $level < 12; $level++) {
            $part = "Content-Type: multipart/mixed; boundary=b{$level}\r\n\r\n--b{$level}\r\n{$part}\r\n--b{$level}--\r\n";
        }

        $raw = "From: a@example.com\r\n{$part}";
        $before = memory_get_usage();
        memory_reset_peak_usage();
        $email = MailParser::parse($raw);
        $growth = memory_get_peak_usage() - $before;

        self::assertCount(1, $email->attachments);
        self::assertSame(strlen($payload), $email->attachments->all()[0]->size);
        self::assertLessThan(8 * strlen($raw), $growth);
    }

    #[Test]
    public function forwardedMessagesCountTowardTheNestingDepth(): void
    {
        $raw = "Subject: leaf\r\n\r\nbody";

        for ($level = 29; $level >= 0; $level--) {
            $raw = "Subject: level {$level}\r\nContent-Type: message/rfc822\r\n\r\n{$raw}";
        }

        $innermost = self::innermost(MailParser::parse($raw, new ParserConfig(maxNestingDepth: 5)));

        self::assertSame(5, $innermost['depth']);
        self::assertSame('level 5', $innermost['email']->subject);
    }

    #[Test]
    public function alternativePartsDoNotResetTheNestingDepth(): void
    {
        $raw = "Subject: leaf\r\n\r\nbody";

        for ($level = 0; $level < 200; $level++) {
            $raw = "Content-Type: multipart/alternative; boundary=b{$level}\r\n\r\n"
                . "--b{$level}\r\nContent-Type: message/rfc822\r\n\r\n{$raw}\r\n--b{$level}--\r\n";
        }

        self::assertSame(25, self::innermost(MailParser::parse($raw))['depth']);
    }

    #[Test]
    public function partsBeyondTheBudgetAreNotRead(): void
    {
        $raw = self::withParts(2000, '');

        self::assertCount(512, MailParser::parse($raw)->attachments);
        self::assertCount(10, MailParser::parse($raw, new ParserConfig(maxPartCount: 10))->attachments);

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, new ParserConfig(strict: true));
    }

    #[Test]
    public function headersBeyondTheMessageBudgetStopTheReading(): void
    {
        $raw = self::withParts(40, str_repeat("X-Filler: y\r\n", 999));

        self::assertCount(17, MailParser::parse($raw)->attachments);

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, new ParserConfig(strict: true));
    }

    #[Test]
    public function aHeaderBlockCutAtItsLimitKeepsEachHeaderOnce(): void
    {
        $block = "X-0: a\r\nX-1: b\r\nX-2: c\r\nX-3: d\r\nX-4: e";
        $headers = (new HeaderParser(new ParserConfig(maxHeaderCount: 3)))->parse($block);

        self::assertSame(['X-0', 'X-1', 'X-2'], array_map(static fn($header): string => $header->name, $headers->all()));
    }

    /**
     * @param list<string> $expected
     */
    #[Test]
    #[DataProvider('senders')]
    public function aSenderIsReadAsOneAddressOrNone(string $from, array $expected): void
    {
        $addresses = MailParser::parse("From: {$from}\r\n\r\nbody")->fromAll->all();

        self::assertSame($expected, array_map(static fn($address): string|null => $address->address ?? null, $addresses));
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function senders(): iterable
    {
        yield 'comment inside the brackets' => ['<evil@x.com (ceo@bank.com)>', ['evil@x.com']];
        yield 'two addresses in one pair of brackets' => ['<evil@x.com, ceo@bank.com>', []];
        yield 'brackets inside brackets' => ['Bank <evil@x.com <ceo@bank.com>>', []];
        yield 'two at signs' => ['ceo@bank.com@x.com', []];
        yield 'a NUL inside' => ["<ceo@bank.com\x00@x.com>", []];
        yield 'quoted local part' => ['<"john doe"@example.com>', ['"john doe"@example.com']];
        yield 'domain literal' => ['<user@[192.0.2.1]>', ['user@[192.0.2.1]']];
        yield 'source route' => ['<@a.example,@b.example:user@example.com>', ['user@example.com']];
        yield 'folding and comments around the parts' => ['<john (c) @ example.com>', ['john@example.com']];
        yield 'UTF-8 address' => ['José <josé@exämple.com>', ['josé@exämple.com']];
        yield 'obsolete dots' => ['john..doe@example.jp', ['john..doe@example.jp']];
        yield 'no domain' => ['<postmaster>', []];
        yield 'an address only in quotes' => ['"alice@bank.example"', []];
        yield 'an encoded word in the brackets' => ['<=?utf-8?Q?alice=40bank.example?=>', []];
        yield 'a zero-width space in the domain' => ["Bank <support@ba\u{200B}nk.example>", []];
        yield 'a soft hyphen in the domain' => ["<support@ba\u{00AD}nk.example>", []];
        yield 'a Hangul filler in the domain' => ["<support@b\u{3164}ank.example>", []];
        yield 'a braille blank in the domain' => ["<support@b\u{2800}ank.example>", []];
        yield 'a control character in a quoted local part' => ["<\"a\x01b\"@bank.example>", []];
        yield 'an internationalized domain' => ["<jos\u{00E9}@ex\u{00E4}mple.com>", ["jos\u{00E9}@ex\u{00E4}mple.com"]];
        yield 'colon left unencoded in an encoded name' => ['=?utf-8?q?Bob_X:_y?= <b@example.com>', ['b@example.com']];
        yield 'comma left unencoded in an encoded name' => ['=?utf-8?q?A,_B?= <b@example.com>, c@example.com', ['b@example.com', 'c@example.com']];
        yield 'list after a bad mailbox' => ['<a@x.com, b@y.com>, c@z.com', ['c@z.com']];
    }

    #[Test]
    public function aLineThatOnlyStartsWithTheBoundaryIsNotADelimiter(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=abc\r\n\r\n"
            . "--abc\r\nContent-Type: text/plain\r\n\r\nline1\r\n--abcdef not a boundary\r\n"
            . "Content-Type: application/x-evil\r\nContent-Disposition: attachment; filename=e.exe\r\n\r\nMZ\r\n"
            . "--abc-- \t\r\n";

        $email = MailParser::parse($raw);

        self::assertCount(0, $email->attachments);
        self::assertStringContainsString('--abcdef not a boundary', (string) $email->text);
    }

    #[Test]
    public function eightBitTextIsAlwaysReadAsUtf8(): void
    {
        $ascii = MailParser::parse("Content-Type: text/plain; charset=us-ascii\r\n\r\ncaf\xe9");
        $unlabelled = MailParser::parse("Content-Type: text/plain\r\n\r\ncaf\xc3\xa9");
        $header = MailParser::parse("Subject: caf\xe9\r\nFrom: Jos\xe9 <j@example.com>\r\n\r\nhi");

        self::assertSame('café', $ascii->text);
        self::assertSame('café', $unlabelled->text);
        self::assertSame('café', $header->subject);
        self::assertSame('José', $header->from?->name);
    }

    #[Test]
    public function aTextAttachmentKeepsTheBytesItWasSentIn(): void
    {
        $bytes = "\xd3\xe1\xc7\xe3";
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\nhi\r\n"
            . "--b\r\nContent-Type: text/plain; charset=windows-1256\r\nContent-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=note.txt\r\n\r\n" . base64_encode($bytes) . "\r\n--b--\r\n";

        $attachment = MailParser::parse($raw)->attachments->all()[0];

        self::assertSame($bytes, $attachment->content());
        self::assertSame(4, $attachment->size);
    }

    #[Test]
    public function brokenBase64CostsAboutItsOwnSize(): void
    {
        $body = str_repeat('A=', 1_000_000);
        $raw = "Content-Type: application/octet-stream\r\nContent-Transfer-Encoding: base64\r\n"
            . "Content-Disposition: attachment; filename=x.bin\r\n\r\n{$body}";
        $before = memory_get_usage();
        memory_reset_peak_usage();

        $attachment = MailParser::parse($raw)->attachments->all()[0];

        self::assertSame('', $attachment->content());
        self::assertLessThan(4 * strlen($raw), memory_get_peak_usage() - $before);
    }

    #[Test]
    public function inlinePiecesOfAMixedBodyAreReadInOrder(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/html\r\n\r\n<p>one</p>\r\n"
            . "--b\r\nContent-Type: image/png\r\nContent-Disposition: inline; filename=a.png\r\n\r\nPNG\r\n"
            . "--b\r\nContent-Type: text/html\r\n\r\n<p>two</p>\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\nfirst\r\n"
            . "--b\r\nContent-Type: text/plain\r\nContent-Disposition: inline\r\n\r\nsecond\r\n--b--\r\n";

        $email = MailParser::parse($raw);

        self::assertSame('<p>one</p><p>two</p><div>first</div><div>second</div>', $email->html);
        self::assertSame("first\r\nsecond", $email->text);
        self::assertSame(['text/html', 'image/png', 'text/html', 'text/plain', 'text/plain'], array_map(static fn($part): string => $part->mimeType, $email->htmlBody));
        self::assertCount(0, $email->attachments);
    }

    #[Test]
    public function anAlternativeNotChosenIsNeitherBodyNorAttachment(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/x-amp-html\r\n\r\n<html amp4email>amp</html>\r\n"
            . "--b\r\nContent-Type: text/html\r\n\r\n<p>html</p>\r\n--b--\r\n";

        $email = MailParser::parse($raw);

        self::assertNull($email->text);
        self::assertSame('<p>html</p>', $email->html);
        self::assertCount(0, $email->attachments);
    }

    #[Test]
    #[DataProvider('invalidContentTypes')]
    public function anInvalidContentTypeIsReadAsPlainText(string $contentType): void
    {
        $email = MailParser::parse("Content-Type:{$contentType}\r\n\r\nhello");

        self::assertSame('hello', $email->text);
        self::assertCount(0, $email->attachments);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidContentTypes(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
        yield 'no subtype' => [' image/'];
        yield 'no type' => [' /png'];
        yield 'space inside' => [' a b/c'];
    }

    #[Test]
    public function encodedWordsInARowJoinWithoutTheSpacesBetweenThem(): void
    {
        $subject = '=?utf-8?q?share?= =?utf-8?q?d_a?=  =?utf-8?q?_post?= and =?utf-8?b?' . base64_encode('more') . '?=';

        self::assertSame('shared a post and more', MailParser::parse("Subject: {$subject}\r\n\r\nhi")->subject);
    }

    #[Test]
    public function anEncodedFilenameIsDecodedOnlyWhenLenient(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: application/pdf;\r\n"
            . " name=\"=?UTF-8?B?w6l0w6kucGRm?=\"\r\nContent-Disposition: attachment;\r\n"
            . " filename=\"=?UTF-8?B?w6l0w6k=?= =?UTF-8?Q?=2Epdf?=\"\r\n\r\nx\r\n--b--\r\n";

        self::assertSame('été.pdf', MailParser::parse($raw)->attachments->all()[0]->filename);
        self::assertSame(
            '=?UTF-8?B?w6l0w6k=?= =?UTF-8?Q?=2Epdf?=',
            MailParser::parse($raw, new ParserConfig(strict: true))->attachments->all()[0]->filename,
        );
    }

    #[Test]
    public function aBoundaryIsNeverDecoded(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=\"=?utf-8?q?x?=\"\r\n\r\n"
            . "--=?utf-8?q?x?=\r\nContent-Type: text/plain\r\n\r\nhello\r\n--=?utf-8?q?x?=--\r\n";

        self::assertSame('hello', MailParser::parse($raw)->text);
    }

    #[Test]
    #[DataProvider('streamedMessages')]
    public function aStreamIsReadExactlyAsTheSameBytesAre(string $raw): void
    {
        $stream = fopen('php://memory', 'r+');
        self::assertNotFalse($stream);
        fwrite($stream, $raw);
        rewind($stream);

        $streamed = (new MailParser())->parseStream($stream);
        $parsed = MailParser::parse($raw);
        fclose($stream);

        self::assertSame($parsed->from?->address, $streamed->from?->address);
        self::assertSame($parsed->text, $streamed->text);
        self::assertSame($parsed->html, $streamed->html);
        self::assertSame(
            array_map(static fn($attachment): string|null => $attachment->filename, $parsed->attachments->all()),
            array_map(static fn($attachment): string|null => $attachment->filename, $streamed->attachments->all()),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function streamedMessages(): iterable
    {
        yield 'header line longer than a read' => [
            'X-Long: ' . str_repeat('a', 8183) . "\r\nFrom: ceo@bank.example\r\nContent-Type: text/html\r\n\r\n<b>x</b>",
        ];
        yield 'delimiter text at a read edge' => [
            "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\n"
            . str_repeat('a', 8100) . "--b\r\nContent-Type: application/x-evil\r\n"
            . "Content-Disposition: attachment; filename=e.exe\r\n\r\nMZ\r\n--b--\r\n",
        ];
        yield 'inner part never closed' => [
            "Content-Type: multipart/mixed; boundary=outer\r\n\r\n--outer\r\n"
            . "Content-Type: multipart/alternative; boundary=inner\r\n\r\n--inner\r\nContent-Type: text/plain\r\n\r\nhi\r\n"
            . "--outer\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=a.pdf\r\n\r\nPDF\r\n--outer--\r\n",
        ];
        yield 'more parts than the budget' => [self::withParts(600, '')];
        yield 'a boundary longer than a read' => [
            'Content-Type: multipart/mixed; boundary=' . str_repeat('Q', 70_000) . "\r\n\r\n--" . str_repeat('Q', 70_000)
            . "\r\nContent-Type: text/plain\r\n\r\nvisible\r\n--" . str_repeat('Q', 70_000)
            . "\r\nContent-Type: application/x-msdownload\r\nContent-Disposition: attachment; filename=evil.exe\r\n\r\nMZ\r\n--" . str_repeat('Q', 70_000) . "--\r\n",
        ];
    }

    #[Test]
    public function lineFeedEndingsParseAsCrlfDoes(): void
    {
        $crlf = "From: a@example.com\r\nSubject: hi\r\nContent-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\none\r\ntwo\r\n"
            . "--b\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename=a.pdf\r\n\r\nPDF\r\n--b--\r\n";
        $expected = MailParser::parse($crlf);

        foreach ([str_replace("\r\n", "\n", $crlf), preg_replace('/\r\n(?=C)/', "\n", $crlf) ?? ''] as $variant) {
            $email = MailParser::parse($variant);

            self::assertSame($expected->text, $email->text);
            self::assertSame($expected->subject, $email->subject);
            self::assertSame($expected->attachments->all()[0]->content(), $email->attachments->all()[0]->content());
        }
    }

    #[Test]
    public function aRepeatedParameterIsReadAtItsFirstOccurrence(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=one; boundary=two\r\n\r\n"
            . "--one\r\nContent-Type: text/plain\r\n\r\nfirst\r\n--two\r\nContent-Type: text/plain\r\n\r\nlast\r\n--two--\r\n--one--\r\n";

        self::assertStringStartsWith('first', (string) MailParser::parse($raw)->text);
    }

    #[Test]
    public function anExtendedFilenameIsPreferredOverThePlainOne(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Disposition: attachment; filename=plain.txt; filename*=utf-8''%C3%A9t%C3%A9.txt\r\n\r\nx\r\n--b--\r\n";

        self::assertSame('été.txt', MailParser::parse($raw)->attachments->all()[0]->filename);
    }

    /**
     * @param non-empty-string $bytes
     */
    #[Test]
    #[DataProvider('legacyLabels')]
    public function aLegacyLabelIsReadAsTheSupersetItsSendersUse(string $label, string $bytes, string $expected): void
    {
        self::assertSame($expected, MailParser::parse("Content-Type: text/plain; charset={$label}\r\n\r\n{$bytes}")->text);
    }

    /**
     * @return iterable<string, array{string, non-empty-string, string}>
     */
    public static function legacyLabels(): iterable
    {
        yield 'latin-1 with Windows quotes' => ['iso-8859-1', "\x93hi\x94 \x80", '“hi” €'];
        yield 'Korean beyond EUC-KR' => ['ks_c_5601-1987', "\x8c\x63", '똠'];
        yield 'Turkish with a euro sign' => ['iso-8859-9', "\x80\xfd", '€ı'];
        yield 'Thai with a euro sign' => ['tis-620', "\x80\xa1", '€ก'];
        yield 'Japanese NEC symbols' => ['shift_jis', "\x87\x40", '①'];
    }

    #[Test]
    public function quotedPrintableWithoutLineBreaksDecodesInBlocks(): void
    {
        $body = str_repeat('=41', 1_000_000);
        $raw = "Content-Type: application/octet-stream\r\nContent-Transfer-Encoding: quoted-printable\r\n"
            . "Content-Disposition: attachment; filename=a.bin\r\n\r\n{$body}";
        $before = memory_get_usage();
        memory_reset_peak_usage();

        $attachment = MailParser::parse($raw, new ParserConfig(storage: ContentStorage::Source))->attachments->all()[0];

        self::assertSame(1_000_000, $attachment->size);
        self::assertLessThan(3 * strlen($raw), memory_get_peak_usage() - $before);
        self::assertSame(str_repeat('A', 1_000_000), $attachment->content());
    }

    #[Test]
    public function forwardedMessagesKeepNoSecondCopyOfThemselves(): void
    {
        $raw = "Subject: leaf\r\n\r\n" . str_repeat('A', 2_000_000);

        for ($level = 0; $level < 45; $level++) {
            $raw = "Subject: level {$level}\r\nContent-Type: message/rfc822\r\n\r\n{$raw}";
        }

        $before = memory_get_usage();
        memory_reset_peak_usage();
        MailParser::parse($raw);

        self::assertLessThan(10 * strlen($raw), memory_get_peak_usage() - $before);
    }

    #[Test]
    public function guessingABoundaryHoldsAThousandCandidatesAtMost(): void
    {
        $body = '';

        for ($line = 0; $line < 300_000; $line++) {
            $body .= "--x{$line}\r\n";
        }

        $raw = "Content-Type: multipart/mixed\r\n\r\n{$body}";
        $before = memory_get_usage();
        memory_reset_peak_usage();
        MailParser::parse($raw);

        self::assertLessThan(6 * strlen($raw), memory_get_peak_usage() - $before);
    }

    #[Test]
    public function aPlainBodyCostsAboutItsOwnText(): void
    {
        $raw = "Content-Type: text/plain\r\n\r\n" . str_repeat('a', 4_000_000);
        $before = memory_get_usage();
        memory_reset_peak_usage();

        $email = MailParser::parse($raw);

        self::assertNull($email->html);
        self::assertLessThan(2 * strlen($raw), memory_get_peak_usage() - $before);
    }

    #[Test]
    public function boundaryScanningStopsAtItsBudget(): void
    {
        $part = "Content-Type: text/plain\r\n\r\n" . str_repeat('-', 3_000_000);

        for ($level = 0; $level < 48; $level++) {
            $part = "Content-Type: multipart/mixed\r\n\r\n{$part}";
        }

        $started = hrtime(true);
        MailParser::parse($part);

        self::assertLessThan(2.0, (hrtime(true) - $started) / 1e9);

        $explicit = "Content-Type: text/plain\r\n\r\n" . str_repeat('-', 3_000_000);

        for ($level = 0; $level < 48; $level++) {
            $explicit = "Content-Type: multipart/mixed; boundary=b{$level}\r\n\r\n--b{$level}\r\n{$explicit}\r\n--b{$level}--\r\n";
        }

        $this->expectException(SecurityLimitException::class);
        MailParser::parse($explicit, new ParserConfig(strict: true, maxNestingDepth: 60));
    }

    #[Test]
    public function aBareCarriageReturnIsDataNotALineBreak(): void
    {
        $email = MailParser::parse("X-Junk: a\rFrom: Bank <alice@bank.example>\r\nSubject: s\r\n\r\nbody");

        self::assertSame(0, $email->fromFieldCount());
        self::assertNull($email->from);
    }

    #[Test]
    public function aHeaderNameIsPrintableAscii(): void
    {
        $email = MailParser::parse("X-\xff\xfe: v\r\nFrom: a@b.example\r\n\r\nbody");

        self::assertFalse($email->hasHeader("X-\xff\xfe"));
        self::assertSame('a@b.example', $email->from?->address);
    }

    #[Test]
    public function aDelimiterFollowedByLongTransportPaddingStillEndsAPart(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\none\r\n"
            . '--b' . str_repeat(" \t", 400) . "\r\nContent-Type: text/plain\r\n\r\ntwo\r\n--b--" . str_repeat(' ', 300) . "\r\n";

        $email = MailParser::parse($raw);

        self::assertSame("one\r\ntwo", $email->text);
    }

    #[Test]
    public function aBodyFullOfDelimitersInsideItsLinesParsesInBoundedTime(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\n" . str_repeat('x--b', 1_500_000) . "\r\n--b--\r\n";

        $started = hrtime(true);
        $email = MailParser::parse($raw);

        self::assertLessThan(0.8, (hrtime(true) - $started) / 1e9);
        self::assertSame(6_000_000, strlen((string) $email->text));
    }

    private static function withParts(int $count, string $extraHeaders): string
    {
        $body = '';

        for ($part = 0; $part < $count; $part++) {
            $body .= "--x\r\nContent-Type: application/octet-stream\r\n{$extraHeaders}"
                . "Content-Disposition: attachment; filename=p{$part}.bin\r\n\r\nz\r\n";
        }

        return "Content-Type: multipart/mixed; boundary=x\r\n\r\n{$body}--x--\r\n";
    }

    /**
     * @return array{email: Email, depth: int}
     */
    private static function innermost(Email $email): array
    {
        $depth = 0;

        while ($email->attachments->count() > 0 && $email->attachments->all()[0]->nestedEmail !== null) {
            $email = $email->attachments->all()[0]->nestedEmail;
            $depth++;
        }

        return ['email' => $email, 'depth' => $depth];
    }
}
