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

use PHPdot\Mail\Parser\Content\ContentDecoder;
use PHPdot\Mail\Parser\Exception\InvalidMimeStructureException;
use PHPdot\Mail\Parser\Exception\SecurityLimitException;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Header\HeaderParser;
use PHPdot\Mail\Parser\ParserConfig;
use PHPdot\Mail\Parser\Result\MimeHeader;

final class MimeParser
{
    private readonly BoundaryDetector $boundaryDetector;

    public function __construct(
        private readonly ParserConfig $config,
        private readonly HeaderParser $headerParser,
        private readonly ContentDecoder $contentDecoder,
    ) {
        $this->boundaryDetector = new BoundaryDetector();
    }

    public function parse(
        string $body,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth = 0,
    ): MimeNode {

        if ($depth > $this->config->maxNestingDepth) {
            if ($this->config->strict) {
                throw new SecurityLimitException(
                    sprintf('MIME nesting depth exceeds maximum of %d', $this->config->maxNestingDepth),
                );
            }

            return new MimeNode($headers, $contentType, $body);
        }

        $primaryType = strtolower(explode('/', $contentType->value)[0]);

        if ($primaryType === 'multipart') {
            return $this->parseMultipart($body, $contentType, $headers, $depth);
        }

        if ($contentType->value === 'message/rfc822') {
            return $this->parseMessageRfc822($body, $contentType, $headers, $depth);
        }

        return new MimeNode($headers, $contentType, $body);
    }

    private function parseMultipart(
        string $body,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth,
    ): MimeNode {
        $boundary = $contentType->getParam('boundary');

        if ($boundary === null || $boundary === '') {
            if ($this->config->strict) {
                throw new InvalidMimeStructureException('Multipart message missing boundary parameter');
            }

            $boundary = $this->detectBoundary($body);
            if ($boundary === null) {
                return new MimeNode($headers, $contentType, $body);
            }
        }

        $split = $this->boundaryDetector->split($body, $boundary);
        $children = [];

        foreach ($split['parts'] as $partRaw) {
            $children[] = $this->parsePart($partRaw, $depth + 1);
        }

        return new MimeNode($headers, $contentType, $body, $children);
    }

    private function parsePart(string $raw, int $depth): MimeNode
    {

        $separator = $this->findHeaderBodySeparator($raw);

        if ($separator === false) {

            $partHeaders = new HeaderCollection();
            $partBody = $raw;
        } else {
            $headerBlock = substr($raw, 0, $separator);
            $partBody = substr($raw, $separator + strlen($this->getLineSeparator($raw)));

            if (str_starts_with($partBody, "\r\n")) {
                $partBody = substr($partBody, 2);
            } elseif (str_starts_with($partBody, "\n")) {
                $partBody = substr($partBody, 1);
            }
            $partHeaders = $this->headerParser->parse($headerBlock);
        }

        $ctHeader = $partHeaders->get('Content-Type');
        $partContentType = $ctHeader !== null && $ctHeader->structured !== null
            ? $ctHeader->structured
            : new MimeHeader('text/plain', ['charset' => 'us-ascii']);

        return $this->parse($partBody, $partContentType, $partHeaders, $depth);
    }

    private function parseMessageRfc822(
        string $body,
        MimeHeader $contentType,
        HeaderCollection $headers,
        int $depth,
    ): MimeNode {

        $transferEncoding = '7bit';
        $teHeader = $headers->get('Content-Transfer-Encoding');
        if ($teHeader !== null) {
            $transferEncoding = strtolower(trim($teHeader->value));
        }

        $decodedBody = $this->contentDecoder->decode($body, $transferEncoding);

        return new MimeNode($headers, $contentType, $decodedBody);
    }

    private function findHeaderBodySeparator(string $raw): int|false
    {

        $pos = strpos($raw, "\r\n\r\n");
        if ($pos !== false) {
            return $pos;
        }

        $pos = strpos($raw, "\n\n");
        if ($pos !== false) {
            return $pos;
        }

        return false;
    }

    private function getLineSeparator(string $text): string
    {
        if (str_contains($text, "\r\n")) {
            return "\r\n";
        }

        return "\n";
    }

    /**
     * The boundary of a multipart body that names none: the first delimiter-shaped line (`--token`, or `--token--`
     * closing) whose token delimits at least twice. Counted in one pass over the lines — counting each candidate over
     * the whole body was quadratic, work a hostile message could ask for on every read.
     */
    private function detectBoundary(string $body): string|null
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $body));
        $seen = [];
        $order = [];

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (!str_starts_with($line, '--') || strlen($line) <= 2 || strlen($line) >= 200) {
                continue;
            }

            $candidate = substr($line, 2);

            if (str_ends_with($candidate, '--')) {
                $candidate = substr($candidate, 0, -2);
            }

            $candidate = trim($candidate);

            if ($candidate === '') {
                continue;
            }

            if (!isset($seen[$candidate])) {
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
