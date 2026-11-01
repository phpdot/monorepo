<?php

declare(strict_types=1);

/**
 * Tracks the current MIME part being parsed during streaming, including body buffering
 * and temporary stream management.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Streaming;

use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Result\MimeHeader;
use RuntimeException;

final class PartContext
{
    public string $bodyBuffer = '';
    public string $pendingCrlf = '';

    /** @var resource|null */
    public $tempStream = null;
    public bool $useStream = false;

    public function __construct(
        public readonly HeaderCollection $headers,
        public readonly MimeHeader $contentType,
    ) {}

    public function isTextBody(): bool
    {
        $mime = $this->contentType->value;

        $dispHeader = $this->headers->get('Content-Disposition');
        if ($dispHeader !== null && $dispHeader->structured !== null) {
            if ($dispHeader->structured->value === 'attachment') {
                return false;
            }
        }

        return in_array($mime, ['text/plain', 'text/html', 'text/calendar'], true);
    }

    public function writeBody(string $data): void
    {
        if ($this->useStream && $this->tempStream !== null) {
            fwrite($this->tempStream, $data);
        } else {
            $this->bodyBuffer .= $data;
        }
    }

    public function flushPendingCrlf(): void
    {
        if ($this->pendingCrlf !== '') {
            $this->writeBody($this->pendingCrlf);
            $this->pendingCrlf = '';
        }
    }

    public function getBody(): string
    {
        if ($this->useStream && $this->tempStream !== null) {
            rewind($this->tempStream);
            $content = stream_get_contents($this->tempStream);
            fclose($this->tempStream);
            $this->tempStream = null;
            return $content !== false ? $content : '';
        }

        return $this->bodyBuffer;
    }

    public function initStream(): void
    {
        $this->useStream = true;
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Failed to open the temporary stream.');
        }

        $this->tempStream = $stream;
    }

    public function __destruct()
    {
        if ($this->tempStream !== null && is_resource($this->tempStream)) {
            fclose($this->tempStream);
        }
    }
}
