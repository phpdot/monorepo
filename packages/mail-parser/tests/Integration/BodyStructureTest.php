<?php

declare(strict_types=1);

/**
 * The body lists and attachments follow RFC 8621 §4.1.4, its own worked example included, with the digest default
 * of RFC 2046 §5.1.5.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\Result\Attachment;
use PHPdot\Mail\Parser\Result\BodyPart;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BodyStructureTest extends TestCase
{
    #[Test]
    public function theRfcsOwnExampleDecomposesAsItSays(): void
    {
        $part = static fn(string $type, string $name, string $disposition = ''): string => "Content-Type: {$type}\r\n"
            . ($disposition === '' ? '' : "Content-Disposition: {$disposition}\r\n") . "X-Name: {$name}\r\n\r\n{$name}\r\n";
        $multipart = static fn(string $type, string $boundary, string ...$parts): string => "Content-Type: multipart/{$type}; boundary={$boundary}\r\n\r\n"
            . implode('', array_map(static fn(string $inner): string => "--{$boundary}\r\n{$inner}", $parts)) . "--{$boundary}--\r\n";

        $raw = $multipart(
            'mixed',
            'm1',
            $part('text/plain', 'A', 'inline'),
            $multipart(
                'mixed',
                'm2',
                $multipart(
                    'alternative',
                    'alt',
                    $multipart('mixed', 'm3', $part('text/plain', 'B', 'inline'), $part('image/jpeg', 'C', 'inline'), $part('text/plain', 'D', 'inline')),
                    $multipart('related', 'rel', $part('text/html', 'E'), $part('image/jpeg', 'F')),
                ),
                $part('image/jpeg', 'G', 'attachment'),
                $part('application/x-excel', 'H'),
                "Content-Type: message/rfc822\r\nX-Name: J\r\n\r\nSubject: J\r\n\r\nJ\r\n",
            ),
            $part('text/plain', 'K', 'inline'),
        );

        $email = MailParser::parse($raw);

        self::assertSame(['A', 'B', 'C', 'D', 'K'], self::names($email->textBody));
        self::assertSame(['A', 'E', 'K'], self::names($email->htmlBody));
        self::assertSame(['C', 'F', 'G', 'H', 'J'], array_map(static fn(Attachment $attachment): string => (string) $attachment->headers?->get('X-Name')?->value, $email->attachments->all()));
        self::assertSame('J', $email->attachments->all()[4]->nestedEmail?->subject);
    }

    #[Test]
    public function aDigestPartWithoutAContentTypeIsAMessage(): void
    {
        $raw = "Content-Type: multipart/digest; boundary=d\r\n\r\n--d\r\n\r\nSubject: first\r\n\r\none\r\n--d\r\n\r\nSubject: second\r\n\r\ntwo\r\n--d--\r\n";

        $subjects = array_map(static fn(Attachment $attachment): string|null => $attachment->nestedEmail?->subject, MailParser::parse($raw)->attachments->all());

        self::assertSame(['first', 'second'], $subjects);
    }

    #[Test]
    public function anImageAfterTheHtmlOfAnAlternativeIsAttached(): void
    {
        $raw = "Content-Type: multipart/alternative; boundary=a\r\n\r\n--a\r\nContent-Type: text/plain\r\n\r\nplain\r\n"
            . "--a\r\nContent-Type: multipart/mixed; boundary=m\r\n\r\n--m\r\nContent-Type: text/html\r\n\r\n<p>html</p>\r\n"
            . "--m\r\nContent-Type: image/png\r\n\r\nPNG\r\n--m\r\nContent-Type: text/plain\r\n\r\nfooter\r\n--m--\r\n--a--\r\n";

        $email = MailParser::parse($raw);

        self::assertSame(['image/png'], array_map(static fn(Attachment $attachment): string => $attachment->mimeType, $email->attachments->all()));
        self::assertSame('plain', $email->text);
    }

    #[Test]
    public function aMultipartThatCannotBeOpenedKeepsItsBytes(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\nhi\r\n"
            . "--b\r\nContent-Type: multipart/related\r\n\r\nno boundary here\r\n--b--\r\n";

        $attachments = MailParser::parse($raw)->attachments->all();

        self::assertCount(1, $attachments);
        self::assertSame('multipart/related', $attachments[0]->mimeType);
        self::assertSame('no boundary here', $attachments[0]->content());
    }

    #[Test]
    public function encodedForwardedMessagesAreReadThreeDeep(): void
    {
        $raw = "Subject: leaf\r\n\r\nbody";

        for ($level = 7; $level >= 0; $level--) {
            $raw = "Subject: level {$level}\r\nContent-Type: message/rfc822\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($raw));
        }

        $email = MailParser::parse($raw);
        $levels = 0;

        while (($nested = $email->attachments->all()[0]->nestedEmail ?? null) !== null) {
            $email = $nested;
            $levels++;
        }

        self::assertSame(3, $levels);
        self::assertSame('message/rfc822', $email->attachments->all()[0]->mimeType);
    }

    #[Test]
    public function anInternationalForwardedMessageIsReadAsOne(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\n--b\r\nContent-Type: text/plain\r\n\r\nsee\r\n"
            . "--b\r\nContent-Type: message/global\r\n\r\nSubject: caf\u{00E9}\r\nFrom: jos\u{00E9}@ex\u{00E4}mple.com\r\n\r\ninner\r\n--b--\r\n";

        $nested = MailParser::parse($raw)->attachments->all()[0]->nestedEmail;

        self::assertSame('café', $nested?->subject);
        self::assertSame('josé@exämple.com', $nested?->from?->address);
    }

    /**
     * @param list<BodyPart> $parts
     *
     * @return list<string>
     */
    private static function names(array $parts): array
    {
        return array_map(static fn(BodyPart $part): string => (string) $part->part->headers?->get('X-Name')?->value, $parts);
    }

}
