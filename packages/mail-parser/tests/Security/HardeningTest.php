<?php

declare(strict_types=1);

/**
 * Unknown charsets never stop a message, hostile headers parse in linear time, quoted parentheses survive, and decoded
 * values carry no line breaks, NULs, paths or bidi overrides.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Security;

use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Source\StringSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HardeningTest extends TestCase
{
    #[Test]
    public function anUnknownCharsetAnywhereStillGivesTheMessage(): void
    {
        $raw = "Subject: =?x-nonsense?B?/w==?= ok\r\nX-Other: =?bogus?Q?a=E9?=\r\n"
            . "Content-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain; charset=x-nonsense\r\n\r\nabc\xff\r\n"
            . "--b\r\nContent-Type: application/pdf\r\nContent-Disposition: attachment; filename*=x-nonsense''%FF.pdf\r\n\r\nx\r\n--b--\r\n";

        $email = MailParser::parse($raw);

        self::assertStringEndsWith('ok', (string) $email->subject);
        self::assertStringStartsWith('abc', (string) $email->text);
        self::assertTrue(mb_check_encoding((string) $email->text, 'UTF-8'));
        self::assertCount(1, $email->attachments);
    }

    #[Test]
    #[DataProvider('hostileHeaders')]
    public function aHostileHeaderParsesInLinearTime(string $raw): void
    {
        $started = hrtime(true);
        MailParser::parse($raw, new ParserConfig(maxHeaderSize: 4_194_304));

        self::assertLessThan(3.0, (hrtime(true) - $started) / 1e9);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostileHeaders(): iterable
    {
        yield 'two megabytes of parameters' => ['Content-Type: text/plain' . str_repeat('; a=b', 400_000) . "\r\n\r\nx"];
        yield 'two megabytes of RFC 2231 sections' => ['Content-Type: text/plain' . implode('', array_map(static fn(int $section): string => "; n*{$section}=v", range(0, 200_000))) . "\r\n\r\nx"];
        yield 'two megabytes of source routes' => ['From: ' . str_repeat('<@a>,', 400_000) . " :\r\n\r\nx"];
        yield 'encoded words in a charset nobody knows' => ['Subject: ' . str_repeat('=?zz-unknown?Q?a?= x ', 100_000) . "\r\n\r\nx"];
        yield 'a quoted line of a million markers' => ["Content-Type: text/plain; format=flowed\r\n\r\n" . str_repeat('>', 1_000_000) . ' text'];
    }

    #[Test]
    public function parenthesesInsideQuotesAreKept(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=\"=_a(b)c\"\r\n\r\n"
            . "--=_a(b)c\r\nContent-Type: text/plain\r\n\r\nhello\r\n"
            . "--=_a(b)c\r\nContent-Type: application/pdf; name=\"Invoice (1).pdf\"\r\n"
            . "Content-Disposition: attachment; filename=\"Invoice (1).pdf\"\r\n\r\nx\r\n--=_a(b)c--\r\n";

        $email = MailParser::parse($raw);

        self::assertSame('hello', $email->text);
        self::assertSame('Invoice (1).pdf', $email->attachments->all()[0]->filename);
    }

    #[Test]
    public function aDecodedValueIsOneLineWithoutNuls(): void
    {
        $email = MailParser::parse("Subject: =?utf-8?q?hi=0D=0ABcc:_x@evil.example=00?=\r\n\r\nbody");

        self::assertSame('hiBcc: x@evil.example', $email->subject);
    }

    #[Test]
    public function aFilenameIsNeverAPathNorABidiTrick(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Disposition: attachment; filename*=utf-8''..%2F..%2Fetc%2Fevil%E2%80%AEfdp.exe%00.pdf\r\n\r\nx\r\n--b--\r\n";

        self::assertSame('evilfdp.exe.pdf', MailParser::parse($raw)->attachments->all()[0]->filename);
    }

    #[Test]
    public function aFlowedQuoteCountsOnlyItsLeadingMarkers(): void
    {
        $email = MailParser::parse("Content-Type: text/plain; format=flowed\r\n\r\n>> deep \r\n>> line\r\n> > shallow");

        self::assertStringContainsString('deep line', (string) $email->text);
        self::assertStringContainsString('> shallow', (string) $email->text);
    }

    #[Test]
    public function aGroupHoldingOnlyACommentEndsTheReading(): void
    {
        $email = MailParser::parse("To: <x@y.com>, group: (some text);, <z@y.com>\r\nSubject: s\r\n\r\nbody");

        self::assertSame(['x@y.com', null, 'z@y.com'], array_map(static fn($to): string|null => $to->address ?? null, $email->to->all()));
    }

    #[Test]
    public function headersBeyondTheMessageByteBudgetStopTheReading(): void
    {
        $part = "--b\r\nContent-Type: text/plain\r\nX-Filler: " . str_repeat('y', 60_000) . "\r\n\r\nz\r\n";
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n" . str_repeat($part, 40) . "--b--\r\n";
        $before = memory_get_usage();
        memory_reset_peak_usage();

        MailParser::parse($raw);

        self::assertLessThan(32 * 1_048_576, memory_get_peak_usage() - $before);
        $this->expectException(SecurityLimitException::class);
        MailParser::parse($raw, new ParserConfig(strict: true, maxTotalHeaderSize: 1_048_576));
    }

    #[Test]
    public function blanksBeforeTheColonStillNameTheField(): void
    {
        $email = MailParser::parse("From: good@x.com\r\nFrom \t: evil@x.com\r\n\r\nbody");

        self::assertCount(2, $email->headerAll('From'));
        self::assertSame(2, $email->fromFieldCount());
    }

    #[Test]
    public function aPartWithHeadersAndNoBodyShowsNoHeadersAsText(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 7bit\r\n\r\n"
            . "--b\r\nContent-Type: text/html; charset=utf-8\r\n\r\n<p>hi</p>\r\n--b--\r\n";

        $email = MailParser::parse($raw);

        self::assertSame('', (string) $email->text);
        self::assertSame('<p>hi</p>', $email->html);
    }

    #[Test]
    public function aStrayLineAmongHeadersEndsNothingBeforeTheBlankLine(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\ncharset=\"utf-8\"\r\nContent-Transfer-Encoding: 8bit\r\n\r\nDear user\r\n--b--\r\n";

        self::assertSame('Dear user', MailParser::parse($raw)->text);
    }

    #[Test]
    public function aParameterNamedByDigitsIsDropped(): void
    {
        $email = MailParser::parse("Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: text/plain; 123=x; charset=utf-8\r\n\r\nhello\r\n"
            . "--b\r\nContent-Disposition: attachment; 7*=utf-8''x; filename=a.txt\r\n\r\nhi\r\n--b--\r\n");

        self::assertSame('hello', $email->text);
        self::assertSame('a.txt', $email->attachments->all()[0]->filename);
    }

    #[Test]
    public function aParameterWithoutAValueDoesNotSwallowTheNext(): void
    {
        $raw = "Content-Type: multipart/mixed; x; boundary=real\r\n\r\n--decoy\r\n--decoy\r\n"
            . "--real\r\nContent-Type: text/plain\r\n\r\nvisible\r\n"
            . "--real\r\nContent-Type: application/x-msdownload\r\nContent-Disposition: attachment; size; filename=\"evil.exe\"\r\n\r\nMZ\r\n--real--\r\n";

        $email = MailParser::parse($raw);

        self::assertSame('visible', $email->text);
        self::assertSame(['evil.exe'], array_map(static fn($attachment): string|null => $attachment->filename, $email->attachments->all()));
    }

    #[Test]
    public function addressListsOfLooseWordsParseInLinearTime(): void
    {
        $raw = 'To: ' . str_repeat('a@b.c ', 64_000) . "\r\nCc: G:" . str_repeat(' a', 64_000) . ";\r\n\r\nx";
        $started = hrtime(true);

        MailParser::parse($raw);

        self::assertLessThan(2.0, (hrtime(true) - $started) / 1e9);
    }

    #[Test]
    #[DataProvider('trickyFilenames')]
    public function aFilenameShowsOnlyWhatItSays(string $encoded, string|null $expected): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Disposition: attachment; filename*=utf-8''{$encoded}\r\n\r\nx\r\n--b--\r\n";

        self::assertSame($expected, MailParser::parse($raw)->attachments->all()[0]->filename);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function trickyFilenames(): iterable
    {
        yield 'isolate override' => ['invoice%E2%81%A7fdp.exe', 'invoicefdp.exe'];
        yield 'left-to-right override' => ['invoice%E2%80%ADx.exe', 'invoicex.exe'];
        yield 'right-to-left mark' => ['invoice%E2%80%8Fx.exe', 'invoicex.exe'];
        yield 'zero-width space' => ['in%E2%80%8Bvoice.exe', 'invoice.exe'];
        yield 'C1 control' => ['a%C2%85b.txt', 'ab.txt'];
        yield 'line separator' => ['a%E2%80%A8b.txt', 'a b.txt'];
        yield 'ideographic spaces before the extension' => ['x.pdf%E3%80%80%E3%80%80%E3%80%80.exe', 'x.pdf .exe'];
        yield 'parent directory' => ['..', null];
        yield 'nothing but a path' => ['a%2F..', null];
        yield 'Hangul fillers' => ['file%E3%85%A4%E3%85%A4.exe', 'file.exe'];
        yield 'combining grapheme joiner' => ['x%CD%8F.pdf', 'x.pdf'];
        yield 'Mongolian vowel separator' => ['%E1%A0%8Ex.exe', 'x.exe'];
        yield 'tag character' => ['a%F3%A0%80%81b.exe', 'ab.exe'];
    }

    #[Test]
    public function anEncodedWordIsDecodedOnceWhateverItsNeighbours(): void
    {
        $inner = base64_encode('=?utf-8?Q?PayPal?=');
        $alone = MailParser::parse("Subject: =?utf-8?B?{$inner}?=\r\n\r\nx")->subject;
        $paired = MailParser::parse("Subject: =?utf-8?B?{$inner}?= =?utf-8?Q?x?=\r\n\r\nx")->subject;

        self::assertSame('=?utf-8?Q?PayPal?=', $alone);
        self::assertSame('=?utf-8?Q?PayPal?=x', $paired);
    }

    #[Test]
    public function onlyTheLineBreakBeforeADelimiterBelongsToIt(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Disposition: attachment; filename=a.bin\r\n\r\nAB\r\n\r\n\r\n--b--\r\n";

        self::assertSame("AB\r\n\r\n", MailParser::parse($raw)->attachments->all()[0]->content());
    }

    #[Test]
    public function aFlowedLineLosesOnlyItsStuffedSpace(): void
    {
        $email = MailParser::parse("Content-Type: text/plain; format=flowed\r\n\r\n    indented\r\n");

        self::assertSame("   indented\r\n", $email->text);
    }

    #[Test]
    public function strictModeRefusesEveryOversizedEntry(): void
    {
        $refused = 0;
        $strict = new ParserConfig(strict: true, maxMessageSize: 100, maxHeaderCount: 3);

        foreach ([
            static fn(): mixed => MailParser::parse(str_repeat('a', 200), $strict),
            static fn(): mixed => (new MailParser($strict))->parseSource(new StringSource(str_repeat('a', 200))),
            static fn(): mixed => MailParser::parse("A: 1\r\nB: 2\r\nC: 3\r\nD: 4\r\n\r\nx", $strict),
        ] as $parse) {
            try {
                $parse();
            } catch (SecurityLimitException) {
                $refused++;
            }
        }

        self::assertSame(3, $refused);
    }

    #[Test]
    public function aTwoByteCharsetIsDecodedNotGuessed(): void
    {
        $text = mb_convert_encoding('héllo', 'UCS-2LE', 'UTF-8');

        self::assertSame('héllo', MailParser::parse("Content-Type: text/plain; charset=ucs-2le\r\n\r\n{$text}")->text);
    }

    #[Test]
    public function aNulInTheBodyBecomesAReplacementCharacter(): void
    {
        self::assertSame("a\u{FFFD}b", MailParser::parse("Content-Type: text/plain\r\n\r\na\0b")->text);
    }

    #[Test]
    public function tensOfThousandsOfEncodedWordsStillDecode(): void
    {
        $subject = MailParser::parse('Subject: ' . str_repeat('=?utf-8?Q?A?= ', 70_000) . "\r\n\r\nx")->subject;

        self::assertSame(70_000, substr_count((string) $subject, 'A'));
        self::assertStringNotContainsString('=?', (string) $subject);
    }
}
