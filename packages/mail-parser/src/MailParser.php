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
use PHPdot\Mail\Parser\Content\ContentDecoder;
use PHPdot\Mail\Parser\Content\FlowedTextFormatter;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\AuthResultsParser;
use PHPdot\Mail\Parser\Header\DkimParser;
use PHPdot\Mail\Parser\Header\Header;
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
use PHPdot\Mail\Parser\Result\Email;
use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPdot\Mail\Parser\Streaming\StreamingParser;

final class MailParser
{
    private readonly ParserConfig $config;
    private readonly HeaderParser $headerParser;
    private readonly MimeParser $mimeParser;
    private readonly ContentDecoder $contentDecoder;
    private readonly CharsetConverter $charsetConverter;
    private readonly FlowedTextFormatter $flowedFormatter;

    public function __construct(ParserConfig|null $config = null)
    {
        $this->config = $config ?? new ParserConfig();
        $this->headerParser = new HeaderParser($this->config);
        $this->contentDecoder = new ContentDecoder();
        $this->charsetConverter = new CharsetConverter();
        $this->mimeParser = new MimeParser($this->config, $this->headerParser, $this->contentDecoder);
        $this->flowedFormatter = new FlowedTextFormatter();
    }

    public static function parse(string $raw, ParserConfig|null $config = null): Email
    {
        $parser = new self($config);

        return $parser->parseEmail($raw);
    }

    public function parseEmail(string $raw, int $depth = 0): Email
    {

        if ($this->config->maxMessageSize > 0 && strlen($raw) > $this->config->maxMessageSize) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('Message size exceeds maximum of %d bytes', $this->config->maxMessageSize),
                );
            }
            $raw = substr($raw, 0, $this->config->maxMessageSize);
        }

        [$headerBlock, $body] = $this->splitMessage($raw);

        $headers = $this->headerParser->parse($headerBlock);

        $contentType = $this->getContentType($headers);

        $rootNode = $this->mimeParser->parse($body, $contentType, $headers);

        return $this->buildEmail($headers, $rootNode, $depth);
    }

    /**
     * Parse from a stream resource using line-by-line streaming.
     *
     * This is a true streaming parser — it reads the stream line by line
     * without loading the entire email into memory. Attachments are buffered
     * via php://temp (auto-spills to disk after 2MB).
     *
     * @param resource $stream
     */
    public function parseStream($stream): Email
    {
        $streamingParser = new StreamingParser(
            $this->config,
            $this->headerParser,
        );

        [$headers, $rootNode] = $streamingParser->parse($stream);

        return $this->buildEmail($headers, $rootNode);
    }

    /**
     * @return array{string, string}
     */
    private function splitMessage(string $raw): array
    {

        $normalized = str_replace("\r\n", "\n", $raw);
        $normalized = str_replace("\r", "\n", $normalized);

        $pos = strpos($normalized, "\n\n");

        if ($pos === false) {

            return [$raw, ''];
        }

        $headerBlock = substr($normalized, 0, $pos);
        $body = substr($normalized, $pos + 2);

        $headerBlock = str_replace("\n", "\r\n", $headerBlock);
        $body = str_replace("\n", "\r\n", $body);

        return [$headerBlock, $body];
    }

    private function getContentType(HeaderCollection $headers): MimeHeader
    {
        $ctHeader = $headers->get('Content-Type');

        if ($ctHeader !== null && $ctHeader->structured !== null) {
            return $ctHeader->structured;
        }

        return new MimeHeader('text/plain', ['charset' => 'us-ascii']);
    }

    private function buildEmail(HeaderCollection $headers, MimeNode $rootNode, int $depth = 0): Email
    {

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

        $text = null;
        $html = null;
        $calendar = null;
        $attachments = [];

        $this->walkMimeTree($rootNode, $text, $html, $calendar, $attachments, $depth);

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
            text: $text,
            html: $html,
            calendar: $calendar,
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
     * Walk the MIME tree to extract text, html, calendar bodies and attachments.
     *
     * @param list<Attachment> $attachments
     */
    private function walkMimeTree(
        MimeNode $node,
        string|null &$text,
        string|null &$html,
        string|null &$calendar,
        array &$attachments,
        int $depth = 0,
    ): void {
        $mimeType = $node->getMimeType();
        $disposition = $node->getDisposition();

        if ($node->isMultipart()) {
            $subType = $node->getSubType();

            if ($subType === 'alternative') {
                $this->walkAlternative($node, $text, $html, $calendar, $attachments, $depth);
            } else {

                foreach ($node->children as $child) {
                    $this->walkMimeTree($child, $text, $html, $calendar, $attachments, $depth);
                }
            }

            return;
        }

        if ($mimeType === 'message/rfc822') {
            $nestedEmail = null;
            if ($depth < $this->config->maxNestingDepth) {
                try {
                    $nestedParser = new self($this->config);
                    $nestedEmail = $nestedParser->parseEmail($node->getRawBody(), $depth + 1);
                } catch (Throwable) {

                }
            }

            $attachments[] = new Attachment(
                mimeType: $mimeType,
                filename: $node->getFilename(),
                disposition: $disposition ?? 'attachment',
                content: $this->config->attachmentEncoding === 'none' ? null : $node->getRawBody(),
                size: $node->getRawBodySize(),
                contentId: $node->getContentId(),
                headers: $node->headers,
                nestedEmail: $nestedEmail,
            );

            return;
        }

        if ($disposition === 'attachment') {
            $this->addAttachment($node, $attachments);
            return;
        }

        if ($mimeType === 'text/plain' && $text === null) {
            $decoded = $node->getDecodedBody($this->contentDecoder, $this->charsetConverter);

            if ($this->config->decodeFlowed) {
                $format = $node->contentType->getParam('format');
                if ($format !== null && strtolower($format) === 'flowed') {
                    $delSp = $node->contentType->getParam('delsp');
                    $decoded = $this->flowedFormatter->decode(
                        $decoded,
                        $delSp !== null && strtolower($delSp) === 'yes',
                    );
                }
            }

            $text = $decoded;
            return;
        }

        if ($mimeType === 'text/html' && $html === null) {
            $html = $node->getDecodedBody($this->contentDecoder, $this->charsetConverter);
            return;
        }

        if ($mimeType === 'text/calendar' && $calendar === null) {
            $calendar = $node->getDecodedBody($this->contentDecoder, $this->charsetConverter);
            return;
        }

        if ($node->getFilename() !== null || $node->getContentId() !== null) {
            $this->addAttachment($node, $attachments);
            return;
        }

        if ($node->getPrimaryType() === 'text' && $text === null && $disposition !== 'inline') {
            $text = $node->getDecodedBody($this->contentDecoder, $this->charsetConverter);
            return;
        }

        if ($node->getPrimaryType() !== 'text') {
            $this->addAttachment($node, $attachments);
        }
    }

    /**
     * @param list<Attachment> $attachments
     */
    private function walkAlternative(
        MimeNode $node,
        string|null &$text,
        string|null &$html,
        string|null &$calendar,
        array &$attachments,
        int $depth = 0,
    ): void {

        foreach ($node->children as $child) {
            $childMime = $child->getMimeType();
            $childDisposition = $child->getDisposition();

            if ($childDisposition === 'attachment') {
                $this->addAttachment($child, $attachments);
                continue;
            }

            if ($child->isMultipart()) {
                $this->walkMimeTree($child, $text, $html, $calendar, $attachments, $depth);
                continue;
            }

            if ($childMime === 'text/plain') {
                $decoded = $child->getDecodedBody($this->contentDecoder, $this->charsetConverter);

                if ($this->config->decodeFlowed) {
                    $format = $child->contentType->getParam('format');
                    if ($format !== null && strtolower($format) === 'flowed') {
                        $delSp = $child->contentType->getParam('delsp');
                        $decoded = $this->flowedFormatter->decode(
                            $decoded,
                            $delSp !== null && strtolower($delSp) === 'yes',
                        );
                    }
                }

                $text = $decoded;
            } elseif ($childMime === 'text/html') {
                $html = $child->getDecodedBody($this->contentDecoder, $this->charsetConverter);
            } elseif ($childMime === 'text/calendar') {
                $calendar = $child->getDecodedBody($this->contentDecoder, $this->charsetConverter);
            } else {
                $this->walkMimeTree($child, $text, $html, $calendar, $attachments);
            }
        }
    }

    /**
     * @param list<Attachment> $attachments
     */
    private function addAttachment(MimeNode $node, array &$attachments): void
    {
        $decoded = $node->getDecodedBody($this->contentDecoder, $this->charsetConverter);
        $size = strlen($decoded);

        if ($this->config->maxAttachmentSize > 0 && $size > $this->config->maxAttachmentSize) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('Attachment size %d exceeds maximum of %d bytes', $size, $this->config->maxAttachmentSize),
                );
            }

            $attachments[] = new Attachment(
                mimeType: $node->getMimeType(),
                filename: $node->getFilename(),
                disposition: $node->getDisposition() ?? 'attachment',
                content: null,
                size: $size,
                contentId: $node->getContentId(),
                headers: $node->headers,
            );
            return;
        }

        $content = match ($this->config->attachmentEncoding) {
            'none' => null,
            'base64' => base64_encode($decoded),
            default => $decoded,
        };

        $attachments[] = new Attachment(
            mimeType: $node->getMimeType(),
            filename: $node->getFilename(),
            disposition: $node->getDisposition() ?? 'attachment',
            content: $content,
            size: $size,
            contentId: $node->getContentId(),
            headers: $node->headers,
        );
    }
}
