<?php

declare(strict_types=1);

/**
 * Recursive MIME tree parser that splits multipart messages by boundary, handles nested parts,
 * and enforces security depth limits.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Mime;

use PHPdot\Mail\Parser\Contract\SourceInterface;
use PHPdot\Mail\Parser\Exception\InvalidMimeStructureException;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\HeaderBlock;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPdot\Mail\Parser\Source\StringSource;

final class MimeParser
{
    private readonly BoundaryDetector $boundaryDetector;

    private const int BOUNDARY_CANDIDATES = 1000;

    private const int DETECT_WINDOW = 65_536;

    private const int SCAN_ALLOWANCE = 16_777_216;

    private int $scanned = 0;

    private int $scanBudget = 0;

    private int $parts = 0;

    private int $headerCount = 0;

    private int $headerBytes = 0;

    public function __construct(
        private readonly ParserConfig $config,
        private readonly HeaderParser $headerParser,
    ) {
        $this->boundaryDetector = new BoundaryDetector();
    }

    public function parse(
        string $body,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth = 0,
    ): MimeNode {
        if ($depth <= $this->config->maxNestingDepth && $this->isMultipart($contentType)) {
            $source = new StringSource($body);

            return $this->parseSource($source, 0, $source->length(), $contentType, $headers, $depth);
        }

        $this->begin($headers, $depth);

        return MimeNode::fromString($headers, $contentType, $body);
    }

    /**
     * Parses the body lying between two offsets of a source, under the headers already read for it.
     */
    public function parseSource(
        SourceInterface $source,
        int $start,
        int $end,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth = 0,
        int $headerBytes = 0,
    ): MimeNode {
        $this->begin($headers, $depth);
        $this->headerBytes += $headerBytes;
        $this->scanBudget = max($this->scanBudget, self::SCAN_ALLOWANCE + 8 * ($end - $start));

        return $this->parseRange($source, $start, $end, $contentType, $headers, $depth);
    }

    private function begin(HeaderCollection $headers, int $depth): void
    {
        if ($depth === 0) {
            $this->parts = 0;
            $this->headerCount = 0;
            $this->headerBytes = 0;
            $this->scanned = 0;
            $this->scanBudget = 0;
        }

        $this->headerCount += $headers->count();
    }

    /**
     * Parses the bytes between two offsets, so each level of a multipart tree reads one shared source and only leaf
     * parts hold a copy of their own.
     */
    private function parseRange(
        SourceInterface $source,
        int $start,
        int $end,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth,
    ): MimeNode {
        if ($depth > $this->config->maxNestingDepth) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('MIME nesting depth exceeds maximum of %d', $this->config->maxNestingDepth),
                );
            }

            return new MimeNode($headers, $contentType, $source, $start, $end);
        }

        if ($this->isMultipart($contentType)) {
            return $this->parseMultipart($source, $start, $end, $contentType, $headers, $depth);
        }

        return new MimeNode($headers, $contentType, $source, $start, $end);
    }

    /**
     * Whether another part may be read: strict mode refuses a message past its part, header or header-byte budget,
     * lenient mode keeps the parts read so far.
     *
     * @throws SecurityLimitException When strict and the message holds more parts or headers than configured.
     */
    private function withinBudget(): bool
    {
        $limit = match (true) {
            $this->config->maxPartCount > 0 && $this->parts >= $this->config->maxPartCount
                => sprintf('Message holds more than %d MIME parts', $this->config->maxPartCount),
            $this->config->maxTotalHeaderCount > 0 && $this->headerCount > $this->config->maxTotalHeaderCount
                => sprintf('Message holds more than %d headers', $this->config->maxTotalHeaderCount),
            $this->config->maxTotalHeaderSize > 0 && $this->headerBytes > $this->config->maxTotalHeaderSize
                => sprintf('Message holds more than %d bytes of headers', $this->config->maxTotalHeaderSize),
            default => null,
        };

        if ($limit !== null && $this->config->strict) {
            throw new SecurityLimitException($limit);
        }

        return $limit === null;
    }

    private function isMultipart(MimeHeader $contentType): bool
    {
        return strtolower(explode('/', $contentType->value)[0]) === 'multipart';
    }

    private function parseMultipart(
        SourceInterface $source,
        int $start,
        int $end,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth,
    ): MimeNode {
        $this->scanned += $end - $start;

        if ($this->scanBudget > 0 && $this->scanned > $this->scanBudget) {
            if ($this->config->strict) {
                throw new SecurityLimitException('Message nests multiparts deeper than its size allows to scan');
            }

            return new MimeNode($headers, $contentType, $source, $start, $end);
        }

        $boundary = $contentType->getParam('boundary');

        if ($boundary === null || $boundary === '') {
            if ($this->config->strict) {
                throw new InvalidMimeStructureException('Multipart message missing boundary parameter');
            }

            $boundary = $this->detectBoundary($source, $start, min($end, $start + self::DETECT_WINDOW));
            if ($boundary === null) {
                return new MimeNode($headers, $contentType, $source, $start, $end);
            }
        }

        $children = [];

        foreach ($this->boundaryDetector->partRanges($source, $boundary, $start, $end) as [$partStart, $partEnd]) {
            if (!$this->withinBudget()) {
                break;
            }

            $this->parts++;
            $children[] = $this->parsePart($source, $partStart, $partEnd, $depth + 1, $contentType->value === 'multipart/digest');
        }

        return new MimeNode($headers, $contentType, $source, $start, $start, $children);
    }

    /**
     * Reads one part; a part of a digest that names no type is a message (RFC 2046 §5.1.5), any other text/plain.
     */
    private function parsePart(SourceInterface $source, int $start, int $end, int $depth, bool $inDigest = false): MimeNode
    {
        [$headerEnd, $bodyStart] = HeaderBlock::bounds($source, $start, $end, $this->config->maxHeaderSize);
        $headerLength = HeaderBlock::readable($headerEnd - $start, $this->config->maxHeaderSize);
        $partHeaders = $headerLength > 0 ? $this->headerParser->parse($source->read($start, $headerLength)) : new HeaderCollection();
        $this->headerBytes += $headerLength;
        $this->headerCount += $partHeaders->count();

        $ctHeader = $partHeaders->get('Content-Type');
        $partContentType = $ctHeader !== null && $ctHeader->structured !== null && $ctHeader->structured->isMediaType()
            ? $ctHeader->structured
            : ($inDigest ? new MimeHeader('message/rfc822') : new MimeHeader('text/plain', ['charset' => 'us-ascii']));

        return $this->parseRange($source, $bodyStart, $end, $partContentType, $partHeaders, $depth);
    }

    /**
     * The boundary of a multipart body that names none: the first `--token` or `--token--` line whose token delimits
     * at least twice within the first 64 KiB of the body, counted in one pass among the first thousand tokens seen.
     */
    private function detectBoundary(SourceInterface $source, int $start, int $end): string|null
    {
        $seen = [];
        $order = [];

        for ($at = $start; $at < $end;) {
            $lineEnd = $source->find("\r\n", $at, $end) ?? $end;
            $line = rtrim($source->read($at, min($lineEnd - $at, 200)));
            $at = $lineEnd + 2;

            if (!str_starts_with($line, '--') || strlen($line) <= 2 || strlen($line) >= 200) {
                continue;
            }

            $candidate = trim(str_ends_with($line, '--') ? substr($line, 2, -2) : substr($line, 2));

            if ($candidate === '') {
                continue;
            }

            if (!isset($seen[$candidate])) {
                if (count($order) >= self::BOUNDARY_CANDIDATES) {
                    continue;
                }

                $seen[$candidate] = 0;
                $order[] = $candidate;
            }

            $seen[$candidate]++;
        }

        foreach ($order as $candidate) {
            if ($seen[$candidate] >= 2) {
                return $candidate;
            }
        }

        return null;
    }
}
