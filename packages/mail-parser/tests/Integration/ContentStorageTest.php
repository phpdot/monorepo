<?php

declare(strict_types=1);

/**
 * Each content storage gives the same attachments, and source storage parses a large message in memory that does not
 * grow with it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\Content\ContentStorage;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentStorageTest extends TestCase
{
    #[Test]
    public function sourceStorageParsesALargeMessageInLittleMemory(): void
    {
        [$stream, $bytes] = self::messageWithAttachment(16);
        $before = memory_get_usage();
        memory_reset_peak_usage();

        $email = (new MailParser(new ParserConfig(storage: ContentStorage::Source)))->parseStream($stream);
        $attachment = $email->attachments->all()[0];

        self::assertLessThan(8 * 1_048_576, memory_get_peak_usage() - $before);
        self::assertSame('hello', $email->text);
        self::assertSame(strlen($bytes), $attachment->size);
        self::assertSame($bytes, stream_get_contents($attachment->stream() ?? self::fail('no stream')));
    }

    #[Test]
    public function everyStorageAgreesOnTheAttachment(): void
    {
        [$stream, $bytes] = self::messageWithAttachment(1);
        $raw = (string) stream_get_contents($stream);

        foreach ([ContentStorage::Memory, ContentStorage::Source] as $storage) {
            $attachment = MailParser::parse($raw, new ParserConfig(storage: $storage))->attachments->all()[0];

            self::assertSame($bytes, $attachment->content());
            self::assertSame(strlen($bytes), $attachment->size);
        }

        $none = MailParser::parse($raw, new ParserConfig(storage: ContentStorage::None))->attachments->all()[0];

        self::assertFalse($none->hasContent());
        self::assertNull($none->content());
        self::assertSame(strlen($bytes), $none->size);
    }

    #[Test]
    public function anAttachmentOverItsLimitKeepsItsSizeAndNoContentInEveryStorage(): void
    {
        [$stream, $bytes] = self::messageWithAttachment(1);
        $raw = (string) stream_get_contents($stream);

        foreach ([ContentStorage::Memory, ContentStorage::Source] as $storage) {
            $attachment = MailParser::parse($raw, new ParserConfig(storage: $storage, maxAttachmentSize: 1000))->attachments->all()[0];

            self::assertFalse($attachment->hasContent());
            self::assertSame(strlen($bytes), $attachment->size);
        }
    }

    /**
     * @return array{resource, string}
     */
    private static function messageWithAttachment(int $megabytes): array
    {
        $bytes = random_bytes($megabytes * 1_048_576);
        $stream = fopen('php://temp', 'w+b');
        self::assertNotFalse($stream);
        fwrite($stream, "From: a@example.com\r\nContent-Type: multipart/mixed; boundary=b\r\n\r\n"
            . "--b\r\nContent-Type: text/plain\r\n\r\nhello\r\n--b\r\nContent-Type: application/octet-stream\r\n"
            . "Content-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=a.bin\r\n\r\n"
            . chunk_split(base64_encode($bytes), 76, "\r\n") . "--b--\r\n");
        rewind($stream);

        return [$stream, $bytes];
    }
}
