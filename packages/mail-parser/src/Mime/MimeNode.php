<?php

declare(strict_types=1);

/**
 * One node of the MIME tree: its headers, and its body as a stretch of the message source, read and decoded only
 * when asked for.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Mime;

use Closure;
use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Content\TransferDecoder;
use PHPdot\Mail\Parser\Contract\SourceInterface;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Result\MimeHeader;
use PHPdot\Mail\Parser\Source\SliceSource;
use PHPdot\Mail\Parser\Source\StringSource;

final class MimeNode
{
    private string|null $decodedBody = null;

    /**
     * @param list<MimeNode> $children
     */
    public function __construct(
        public readonly HeaderCollection $headers,
        public readonly MimeHeader $contentType,
        private readonly SourceInterface $source,
        private readonly int $start,
        private readonly int $end,
        public readonly array $children = [],
    ) {}

    /**
     * @param list<MimeNode> $children
     */
    public static function fromString(HeaderCollection $headers, MimeHeader $contentType, string $body, array $children = []): self
    {
        return new self($headers, $contentType, new StringSource($body, false), 0, strlen($body), $children);
    }

    public function isMultipart(): bool
    {
        return $this->getPrimaryType() === 'multipart';
    }

    public function getPrimaryType(): string
    {
        $slash = strpos($this->contentType->value, '/');
        if ($slash === false) {
            return $this->contentType->value;
        }

        return substr($this->contentType->value, 0, $slash);
    }

    public function getSubType(): string
    {
        $slash = strpos($this->contentType->value, '/');
        if ($slash === false) {
            return '';
        }

        return substr($this->contentType->value, $slash + 1);
    }

    public function getMimeType(): string
    {
        return $this->contentType->value;
    }

    public function getTransferEncoding(): string
    {
        $header = $this->headers->get('Content-Transfer-Encoding');

        return $header !== null ? strtolower(trim($header->value)) : '7bit';
    }

    public function getDisposition(): string|null
    {
        $header = $this->headers->get('Content-Disposition');
        if ($header === null) {
            return null;
        }

        if ($header->structured !== null) {
            return $header->structured->value;
        }

        return strtolower(trim(explode(';', $header->value)[0]));
    }

    public function getFilename(): string|null
    {
        $disposition = $this->headers->get('Content-Disposition');
        $filename = null;

        if ($disposition !== null && $disposition->structured !== null) {
            $filename = $disposition->structured->getParam('filename');
        }

        $filename ??= $this->contentType->getParam('name');

        return $filename === null ? null : $this->sanitizeFilename(mb_scrub($filename, 'UTF-8'));
    }

    /**
     * A filename as display data: its last path segment without control, format, unassigned or blank-looking characters,
     * spaces of any kind collapsed to one; nothing when no name is left.
     */
    private function sanitizeFilename(string $filename): string|null
    {
        $base = basename(str_replace('\\', '/', $filename));
        $clean = preg_replace('/[\p{Cc}\p{Cf}\p{Co}\p{Cn}\x{034F}\x{115F}\x{1160}\x{180E}\x{2800}\x{3164}\x{FFA0}\x{FE00}-\x{FE0F}\x{E0100}-\x{E01EF}]/u', '', $base) ?? '';
        $clean = trim(preg_replace('/[\p{Z}\s]+/u', ' ', $clean) ?? '');

        return $clean === '' || $clean === '.' || $clean === '..' ? null : $clean;
    }

    public function getContentId(): string|null
    {
        $header = $this->headers->get('Content-ID');
        if ($header === null) {
            return null;
        }

        $value = trim($header->value);
        if (str_starts_with($value, '<') && str_ends_with($value, '>')) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    public function getDecodedBody(CharsetConverter $charsetConverter): string
    {
        if ($this->decodedBody !== null) {
            return $this->decodedBody;
        }

        $content = $this->getContent();

        if ($this->getPrimaryType() === 'text') {
            $charset = $this->contentType->getParam('charset') ?? 'us-ascii';
            $content = $charsetConverter->toUtf8($content, $charset);
        }

        $this->decodedBody = $content;

        return $content;
    }

    /**
     * The body as sent, its transfer encoding undone and its bytes left in their own charset, as a file is saved.
     */
    public function getContent(): string
    {
        if (!$this->isTransferEncoded()) {
            return $this->source->read($this->start, $this->end - $this->start);
        }

        $content = '';
        $this->decodeInto(static function (string $bytes) use (&$content): void {
            $content .= $bytes;
        });

        return $content;
    }

    /**
     * How many bytes the body holds once decoded, counted a block at a time.
     */
    public function getContentSize(): int
    {
        return $this->decodeInto(static function (string $bytes): void {});
    }

    /**
     * The decoded body written into a fresh php://temp stream, rewound, holding at most 2 MiB in memory.
     *
     * @return resource|null
     */
    public function openContent()
    {
        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($stream === false) {
            return null;
        }

        $this->decodeInto(static function (string $bytes) use ($stream): void {
            fwrite($stream, $bytes);
        });
        rewind($stream);

        return $stream;
    }

    public function isTransferEncoded(): bool
    {
        return in_array($this->getTransferEncoding(), ['base64', 'quoted-printable'], true);
    }

    /**
     * The body read as a message of its own: the same source in place when it is not transfer-encoded.
     */
    public function bodySource(): SourceInterface
    {
        return $this->isTransferEncoded()
            ? new StringSource($this->getContent())
            : new SliceSource($this->source, $this->start, $this->end);
    }

    public function getRawBody(): string
    {
        return $this->source->read($this->start, $this->end - $this->start);
    }

    public function getRawBodySize(): int
    {
        return $this->end - $this->start;
    }

    /**
     * @param Closure(string): void $sink
     */
    private function decodeInto(Closure $sink): int
    {
        return TransferDecoder::decode($this->source, $this->start, $this->end, $this->getTransferEncoding(), $sink);
    }
}
