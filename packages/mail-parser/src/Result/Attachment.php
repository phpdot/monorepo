<?php

declare(strict_types=1);

/**
 * Value object representing a parsed email attachment with MIME type, filename, decoded content,
 * stream access for large files, and optional nested email support.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use PHPdot\Mail\Parser\Header\HeaderCollection;

final class Attachment
{
    /** @var resource|null */
    private $streamResource = null;

    public function __construct(
        public readonly string $mimeType,
        public readonly string|null $filename,
        public readonly string $disposition,
        public readonly string|null $content,
        public readonly int $size,
        public readonly string|null $contentId = null,
        public readonly HeaderCollection|null $headers = null,
        public readonly Email|null $nestedEmail = null,
    ) {}

    /**
     * Get the attachment content as a readable stream resource.
     *
     * This creates a php://temp stream from the content on first call
     * and returns it. For large attachments, this avoids holding a second
     * copy of the content in memory — the stream can be piped directly
     * to S3, filesystem, or any stream-compatible destination.
     *
     * @return resource|null A readable stream, or null if content is not available.
     */
    public function stream()
    {

        if ($this->streamResource !== null && is_resource($this->streamResource)) {
            rewind($this->streamResource);
            return $this->streamResource;
        }

        if ($this->content === null) {
            return null;
        }

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return null;
        }

        fwrite($stream, $this->content);
        rewind($stream);

        $this->streamResource = $stream;

        return $stream;
    }

    /**
     * Create an Attachment with a pre-existing stream resource.
     *
     * Used by the streaming parser to avoid loading content into memory.
     * The content property is lazily read from the stream on first access.
     *
     * @param resource $stream
     */
    public static function fromStream(
        string $mimeType,
        string|null $filename,
        string $disposition,
        $stream,
        int $size,
        string|null $contentId = null,
        HeaderCollection|null $headers = null,
        Email|null $nestedEmail = null,
    ): self {
        $attachment = new self(
            mimeType: $mimeType,
            filename: $filename,
            disposition: $disposition,
            content: null,
            size: $size,
            contentId: $contentId,
            headers: $headers,
            nestedEmail: $nestedEmail,
        );

        $attachment->streamResource = $stream;

        return $attachment;
    }

    /**
     * Get content from the stream if content property is null but stream exists.
     *
     * This is useful when an Attachment was created via fromStream() —
     * calling getContent() reads the stream into a string.
     */
    public function getContent(): string|null
    {
        if ($this->content !== null) {
            return $this->content;
        }

        if ($this->streamResource !== null && is_resource($this->streamResource)) {
            rewind($this->streamResource);
            $content = stream_get_contents($this->streamResource);
            return $content !== false ? $content : null;
        }

        return null;
    }

    public function __destruct()
    {
        if ($this->streamResource !== null && is_resource($this->streamResource)) {
            fclose($this->streamResource);
        }
    }
}
