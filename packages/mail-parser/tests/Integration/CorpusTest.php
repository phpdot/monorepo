<?php

declare(strict_types=1);

/**
 * Every message of the corpus parses to its recorded JSON view, and to the same view with LF line endings, read from
 * a stream, and with its content left in its source; MAIL_PARSER_RECORD=1 records the views anew.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Tests\Integration;

use PHPdot\Mail\Parser\Content\ContentStorage;
use PHPdot\Mail\Parser\MailParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;
use PHPdot\Mail\Parser\Result\Attachment;
use PHPdot\Mail\Parser\Result\BodyPart;
use PHPdot\Mail\Parser\Result\Email;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CorpusTest extends TestCase
{
    private const string CORPUS = __DIR__ . '/../Fixtures/corpus';

    #[Test]
    #[DataProvider('messages')]
    public function aMessageParsesToItsRecordedView(string $path): void
    {
        $raw = (string) file_get_contents($path);
        $view = self::view(MailParser::parse($raw));
        $recorded = substr($path, 0, -4) . '.json';
        $json = json_encode($view, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";

        if (getenv('MAIL_PARSER_RECORD') === '1') {
            file_put_contents($recorded, $json);
        }

        self::assertFileExists($recorded);
        self::assertSame((string) file_get_contents($recorded), $json);
        self::assertSame($view, self::view(MailParser::parse(str_replace("\r\n", "\n", $raw))));
        self::assertSame($view, self::view(MailParser::parse($raw, new ParserConfig(storage: ContentStorage::Source))));

        $stream = fopen('php://memory', 'w+b');
        self::assertNotFalse($stream);
        fwrite($stream, $raw);
        rewind($stream);

        self::assertSame($view, self::view((new MailParser())->parseStream($stream)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function messages(): iterable
    {
        foreach (glob(self::CORPUS . '/*.eml') ?: [] as $path) {
            yield basename($path, '.eml') => [$path];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function view(Email $email): array
    {
        $address = static fn(Address|AddressGroup $one): array => $one instanceof AddressGroup
            ? ['group' => $one->name, 'members' => array_map(static fn(Address $member): array => [$member->address, $member->name], $one->addresses)]
            : [$one->address, $one->name];
        $body = static fn(BodyPart $part): array => [$part->mimeType, $part->text];

        return [
            'subject' => $email->subject,
            'from' => array_map($address, $email->fromAll->all()),
            'fromFields' => count($email->headerAll('From')),
            'to' => array_map($address, $email->to->all()),
            'text' => $email->text,
            'html' => $email->html,
            'textBody' => array_map($body, $email->textBody),
            'htmlBody' => array_map($body, $email->htmlBody),
            'attachments' => array_map(static fn(Attachment $attachment): array => [
                'type' => $attachment->mimeType,
                'filename' => $attachment->filename,
                'disposition' => $attachment->disposition,
                'size' => $attachment->size,
                'sha256' => hash('sha256', (string) $attachment->content()),
                'nested' => $attachment->nestedEmail?->subject,
            ], $email->attachments->all()),
        ];
    }
}
