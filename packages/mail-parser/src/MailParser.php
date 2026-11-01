<?php

declare(strict_types=1);

/**
 * Main entry point for parsing EML/MIME email messages.
 * Provides both string-based and stream-based parsing with configurable options.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Content\ContentStorage;
use PHPdot\Mail\Parser\Content\FlowedTextFormatter;
use PHPdot\Mail\Parser\Contract\SourceInterface;
use PHPdot\Mail\Parser\Exception\InvalidHeaderException;
use PHPdot\Mail\Parser\Exception\ParseException;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\AuthResultsParser;
use PHPdot\Mail\Parser\Header\DkimParser;
use PHPdot\Mail\Parser\Header\Header;
use PHPdot\Mail\Parser\Header\HeaderBlock;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\Header\ReceivedHeaderParser;
use PHPdot\Mail\Parser\Header\SpfParser;
use PHPdot\Mail\Parser\Mime\MimeNode;
use PHPdot\Mail\Parser\Mime\MimeParser;
use PHPdot\Mail\Parser\Result\Address;
use PHPdot\Mail\Parser\Result\AddressGroup;
use PHPdot\Mail\Parser\Result\AddressList;
use PHPdot\Mail\Parser\Result\Attachment;
use PHPdot\Mail\Parser\Result\AttachmentList;
use PHPdot\Mail\Parser\Result\BodyPart;
use PHPdot\Mail\Parser\Result\Email;
use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPdot\Mail\Parser\Source\SliceSource;
use PHPdot\Mail\Parser\Source\StreamSource;
use PHPdot\Mail\Parser\Source\StringSource;

final class MailParser
{
    private readonly ParserConfig $config;
    private readonly HeaderParser $headerParser;
    private readonly MimeParser $mimeParser;
    private readonly CharsetConverter $charsetConverter;
    private readonly FlowedTextFormatter $flowedFormatter;

    public function __construct(ParserConfig|null $config = null)
    {
        $this->config = $config ?? new ParserConfig();
        $this->headerParser = new HeaderParser($this->config);
        $this->charsetConverter = new CharsetConverter();
        $this->mimeParser = new MimeParser($this->config, $this->headerParser);
        $this->flowedFormatter = new FlowedTextFormatter();
    }

    public static function parse(string $raw, ParserConfig|null $config = null): Email
    {
        $parser = new self($config);

        return $parser->parseEmail($raw);
    }

    /**
     * Parses a message held in memory; past `maxMessageSize` strict mode refuses it and lenient mode reads what fits.
     *
     * @throws SecurityLimitException When strict and the message is larger than allowed.
     */
    public function parseEmail(string $raw, int $depth = 0): Email
    {
        if ($this->config->maxMessageSize > 0 && strlen($raw) > $this->config->maxMessageSize) {
            $this->refuseOversize();
            $raw = substr($raw, 0, $this->config->maxMessageSize);
        }

        return $this->parseSource(new StringSource($raw), $depth);
    }

    /**
     * Parses a message read from a stream into a temporary spool, so its size in memory never depends on its size.
     *
     * @param resource $stream
     *
     * @throws SecurityLimitException When strict and the stream holds more than `maxMessageSize` bytes.
     */
    public function parseStream($stream): Email
    {
        $source = StreamSource::spool($stream, $this->config->maxMessageSize);

        if ($source->truncated) {
            $this->refuseOversize();
        }

        return $this->parseSource($source);
    }

    /**
     * Parses a message from its source: the headers up to the first blank line, then the MIME tree under them.
     */
    public function parseSource(SourceInterface $source, int $depth = 0, int $encodedDepth = 0): Email
    {
        if ($depth === 0 && $this->config->maxMessageSize > 0 && $source->length() > $this->config->maxMessageSize) {
            $this->refuseOversize();
            $source = new SliceSource($source, 0, $this->config->maxMessageSize);
        }

        $length = $source->length();
        [$headerEnd, $bodyStart] = HeaderBlock::bounds($source, 0, $length, $this->config->maxHeaderSize);
        $limit = HeaderBlock::readable($headerEnd, $this->config->maxHeaderSize);
        $headers = $this->headerParser->parse($source->read(0, $limit));

        $rootNode = $this->mimeParser->parseSource($source, $bodyStart, $length, $this->getContentType($headers), $headers, $depth, $limit);

        return $this->buildEmail($headers, $rootNode, $depth, $encodedDepth);
    }

    /**
     * @throws SecurityLimitException When strict.
     */
    private function refuseOversize(): void
    {
        if ($this->config->strict) {
            throw new SecurityLimitException(
                sprintf('Message size exceeds maximum of %d bytes', $this->config->maxMessageSize),
            );
        }
    }

    private function getContentType(HeaderCollection $headers): MimeHeader
    {
        $ctHeader = $headers->get('Content-Type');

        if ($ctHeader !== null && $ctHeader->structured !== null && $ctHeader->structured->isMediaType()) {
            return $ctHeader->structured;
        }

        return new MimeHeader('text/plain', ['charset' => 'us-ascii']);
    }

    /**
     * @throws InvalidHeaderException When strict and the message carries more than one From field (RFC 5322 §3.6).
     */
    private function buildEmail(HeaderCollection $headers, MimeNode $rootNode, int $depth = 0, int $encodedDepth = 0): Email
    {
        if ($this->config->strict && count($headers->getAll('From')) > 1) {
            throw new InvalidHeaderException('Message carries more than one From field');
        }

        $fromAddresses = $this->getAddresses($headers, 'From');
        $from = $this->extractPrimaryAddress($fromAddresses);
        $fromAll = new AddressList($fromAddresses);
        $sender = $this->extractPrimaryAddress($this->getAddresses($headers, 'Sender'));

        $to = new AddressList($this->getAddresses($headers, 'To'));
        $cc = new AddressList($this->getAddresses($headers, 'Cc'));
        $bcc = new AddressList($this->getAddresses($headers, 'Bcc'));
        $replyTo = new AddressList($this->getAddresses($headers, 'Reply-To'));

        $subject = $headers->get('Subject')?->value;
        $messageId = $this->extractAngleBracketValue($headers->get('Message-ID')?->value);
        $inReplyTo = $this->extractAngleBracketValue($headers->get('In-Reply-To')?->value);
        $references = $this->parseReferences($headers->get('References')?->value);
        $date = $headers->get('Date')?->dateTime;

        $textNodes = [];
        $htmlNodes = [];
        $attachmentNodes = [];
        $this->parseStructure([$rootNode], 'mixed', false, true, true, $textNodes, $htmlNodes, $attachmentNodes, $depth - 1);
        $textBody = $this->bodyParts($textNodes);
        $htmlBody = $this->bodyParts($htmlNodes);
        $attachments = [];

        foreach ($attachmentNodes as [$node, $at]) {
            $nested = null;

            $encoded = $encodedDepth + ($node->isTransferEncoded() ? 1 : 0);

            if (in_array($node->getMimeType(), ['message/rfc822', 'message/global'], true) && $at < $this->config->maxNestingDepth && $encoded <= $this->config->maxEncodedNesting) {
                try {
                    $nested = $this->parseSource($node->bodySource(), $at + 1, $encoded);
                } catch (ParseException $failure) {
                    $nested = $this->config->strict ? throw $failure : null;
                }
            }

            $attachments[] = $this->attachmentOf($node, $nested, lazy: $nested !== null);
        }

        $receivedParser = new ReceivedHeaderParser($this->headerParser->getDateParser());
        $received = array_map(
            fn(Header $h) => $receivedParser->parse($h->value),
            $headers->getAll('Received'),
        );

        $dkimParser = new DkimParser();
        $dkim = array_map(
            fn(Header $h) => $dkimParser->parse($h->value),
            $headers->getAll('DKIM-Signature'),
        );

        $spfParser = new SpfParser();
        $spfHeader = $headers->get('Received-SPF');
        $spf = $spfHeader !== null ? $spfParser->parse($spfHeader->value) : null;

        $authResultsParser = new AuthResultsParser();
        $authResults = array_map(
            fn(Header $h) => $authResultsParser->parse($h->value),
            $headers->getAll('Authentication-Results'),
        );

        return new Email(
            subject: $subject,
            messageId: $messageId,
            date: $date,
            from: $from,
            fromAll: $fromAll,
            sender: $sender,
            to: $to,
            cc: $cc,
            bcc: $bcc,
            replyTo: $replyTo,
            inReplyTo: $inReplyTo,
            references: $references,
            text: self::joinedText($textBody),
            html: self::joinedHtml($htmlBody),
            calendar: $this->calendarOf($rootNode),
            textBody: $textBody,
            htmlBody: $htmlBody,
            attachments: new AttachmentList($attachments),
            received: $received,
            dkim: $dkim,
            spf: $spf,
            authResults: $authResults,
            headerCollection: $headers,
        );
    }

    /**
     * @param list<Address|AddressGroup> $addresses
     */
    private function extractPrimaryAddress(array $addresses): Address|null
    {
        foreach ($addresses as $addr) {
            if ($addr instanceof Address) {
                return $addr;
            }
        }

        return null;
    }

    /**
     * Parse References header into list of bare Message-IDs.
     *
     * @return list<string>
     */
    private function parseReferences(string|null $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        preg_match_all('/<([^>]+)>/', $raw, $matches);

        return $matches[1];
    }

    /**
     * @return list<Address|AddressGroup>
     */
    private function getAddresses(HeaderCollection $headers, string $name): array
    {
        $header = $headers->get($name);
        $addresses = $header === null ? null : $header->addresses;

        return $addresses ?? [];
    }

    private function extractAngleBracketValue(string|null $value): string|null
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if (str_starts_with($value, '<') && str_ends_with($value, '>')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Sorts parts into the body lists and attachments as RFC 8621 §4.1.4 does; an alternative's other text renderings
     * are neither, and a multipart that could not be opened is kept as an attachment.
     *
     * @param list<MimeNode> $parts
     * @param list<MimeNode> $text
     * @param list<MimeNode> $html
     * @param list<array{MimeNode, int}> $attachments
     */
    private function parseStructure(
        array $parts,
        string $multipartType,
        bool $inAlternative,
        bool $textOn,
        bool $htmlOn,
        array &$text,
        array &$html,
        array &$attachments,
        int $depth,
    ): void {
        $textLength = $textOn ? count($text) : -1;
        $htmlLength = $htmlOn ? count($html) : -1;

        foreach ($parts as $index => $part) {
            $type = $part->getMimeType();
            $media = self::isInlineMedia($type);
            $inline = $part->getDisposition() !== 'attachment'
                && ($type === 'text/plain' || $type === 'text/html' || $media)
                && ($index === 0 || ($multipartType !== 'related' && ($media || $part->getFilename() === null)));

            if ($part->isMultipart() && ($part->children !== [] || $part->getRawBodySize() === 0)) {
                $subType = $part->getSubType();
                $this->parseStructure($part->children, $subType, $inAlternative || $subType === 'alternative', $textOn, $htmlOn, $text, $html, $attachments, $depth + 1);

                continue;
            }

            if (!$inline) {
                $rendering = $multipartType === 'alternative' && $part->getPrimaryType() === 'text'
                    && $part->getFilename() === null && $part->getDisposition() !== 'attachment';

                if (!$rendering) {
                    $attachments[] = [$part, $depth + 1];
                }

                continue;
            }

            if ($multipartType === 'alternative') {
                match (true) {
                    $type === 'text/plain' && $textOn => $text[] = $part,
                    $type === 'text/html' && $htmlOn => $html[] = $part,
                    $type === 'text/plain', $type === 'text/html' => null,
                    default => $attachments[] = [$part, $depth + 1],
                };

                continue;
            }

            if ($inAlternative && $type === 'text/plain') {
                $htmlOn = false;
            }

            if ($inAlternative && $type === 'text/html') {
                $textOn = false;
            }

            if ($textOn) {
                $text[] = $part;
            }

            if ($htmlOn) {
                $html[] = $part;
            }

            if ((!$textOn || !$htmlOn) && $media) {
                $attachments[] = [$part, $depth + 1];
            }
        }

        if ($multipartType === 'alternative' && $textOn && $htmlOn) {
            if ($textLength === count($text) && $htmlLength !== count($html)) {
                array_push($text, ...array_slice($html, $htmlLength));
            }

            if ($htmlLength === count($html) && $textLength !== count($text)) {
                array_push($html, ...array_slice($text, $textLength));
            }
        }
    }

    /**
     * The plain-text parts of the text body joined, or null when it holds none.
     *
     * @param list<BodyPart> $parts
     */
    private static function joinedText(array $parts): string|null
    {
        $texts = array_values(array_filter(array_map(
            static fn(BodyPart $part): string|null => $part->mimeType === 'text/plain' ? $part->text : null,
            $parts,
        ), static fn(string|null $text): bool => $text !== null));

        $nonEmpty = array_values(array_filter($texts, static fn(string $text): bool => $text !== ''));

        return match (count($nonEmpty)) {
            0 => $texts === [] ? null : '',
            1 => $nonEmpty[0],
            default => implode("\r\n", $nonEmpty),
        };
    }

    /**
     * The HTML body's parts joined in order, plain text among them escaped, or null when it holds no HTML part.
     *
     * @param list<BodyPart> $parts
     */
    private static function joinedHtml(array $parts): string|null
    {
        $htmlParts = array_filter($parts, static fn(BodyPart $part): bool => $part->mimeType === 'text/html');

        if ($htmlParts === []) {
            return null;
        }

        if (count($parts) === 1) {
            return $parts[0]->text;
        }

        $html = '';

        foreach ($parts as $part) {
            if ($part->mimeType === 'text/html') {
                $html .= $part->text;
            } elseif ($part->mimeType === 'text/plain' && $part->text !== null && $part->text !== '') {
                $html .= '<div>' . nl2br(htmlspecialchars($part->text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</div>';
            }
        }

        return $html;
    }

    private static function isInlineMedia(string $type): bool
    {
        return str_starts_with($type, 'image/') || str_starts_with($type, 'audio/') || str_starts_with($type, 'video/');
    }

    /**
     * A body part's decoded text: plain text with format=flowed undone, HTML as sent, a NUL made U+FFFD as browsers do.
     */
    private function bodyText(MimeNode $node): string|null
    {
        if ($node->getPrimaryType() !== 'text') {
            return null;
        }

        $decoded = $node->getDecodedBody($this->charsetConverter);
        $format = $node->contentType->getParam('format');

        if ($this->config->decodeFlowed && $node->getMimeType() === 'text/plain' && $format !== null && strtolower($format) === 'flowed') {
            $delSp = $node->contentType->getParam('delsp');
            $decoded = $this->flowedFormatter->decode($decoded, $delSp !== null && strtolower($delSp) === 'yes');
        }

        return str_contains($decoded, "\0") ? str_replace("\0", "\u{FFFD}", $decoded) : $decoded;
    }

    /**
     * @param list<MimeNode> $nodes
     *
     * @return list<BodyPart>
     */
    private function bodyParts(array $nodes): array
    {
        return array_map(fn(MimeNode $node): BodyPart => new BodyPart(
            mimeType: $node->getMimeType(),
            text: $this->bodyText($node),
            part: $this->attachmentOf($node, lazy: true),
        ), $nodes);
    }

    /**
     * The first calendar part not sent as an attachment, decoded: the invitation an iMIP message carries.
     */
    private function calendarOf(MimeNode $node): string|null
    {
        if ($node->getMimeType() === 'text/calendar' && $node->getDisposition() !== 'attachment') {
            return $node->getDecodedBody($this->charsetConverter);
        }

        foreach ($node->children as $child) {
            $found = $this->calendarOf($child);

            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * A part's attachment view; a body part's, or a forwarded message parsed already, is read lazily.
     *
     * @throws SecurityLimitException When strict and the part decodes larger than allowed.
     */
    private function attachmentOf(MimeNode $node, Email|null $nestedEmail = null, bool $lazy = false): Attachment
    {
        $storage = $lazy && $this->config->storage === ContentStorage::Memory ? ContentStorage::Source : $this->config->storage;
        $limit = $this->config->maxAttachmentSize;
        $size = $storage === ContentStorage::Memory && ($limit === 0 || $node->getRawBodySize() <= $limit) ? null : $node->getContentSize();
        $over = $limit > 0 && $size !== null && $size > $limit;
        $content = $storage === ContentStorage::Memory && !$over ? $node->getContent() : null;
        $size ??= strlen((string) $content);

        if ($over && $this->config->strict) {
            throw new SecurityLimitException(
                sprintf('Attachment size %d exceeds maximum of %d bytes', $size, $this->config->maxAttachmentSize),
            );
        }

        return new Attachment(
            mimeType: $node->getMimeType(),
            filename: $node->getFilename(),
            disposition: $node->getDisposition() ?? 'attachment',
            size: $size,
            contentId: $node->getContentId(),
            headers: $node->headers,
            nestedEmail: $nestedEmail,
            content: $over ? null : match ($storage) {
                ContentStorage::Memory => $content,
                ContentStorage::Source => $node->openContent(...),
                ContentStorage::None => null,
            },
        );
    }
}
