<?php

declare(strict_types=1);

/**
 * Represents a single node in the MIME tree with lazy content decoding, charset conversion,
 * and access to part metadata.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Mime;

use PHPdot\Mail\Parser\Content\CharsetConverter;
use PHPdot\Mail\Parser\Content\ContentDecoder;
use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Result\Email;
use PHPdot\Mail\Parser\Result\MimeHeader;

final class MimeNode
{
    private string|null $decodedBody = null;

    /**
     * @param list<MimeNode> $children
     */
    public function __construct(
        public readonly HeaderCollection $headers,
        public readonly MimeHeader $contentType,
        private readonly string $rawBody,
        public readonly array $children = [],
        public readonly Email|null $nestedMessage = null,
    ) {}

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
        if ($disposition !== null && $disposition->structured !== null) {
            $filename = $disposition->structured->getParam('filename');
            if ($filename !== null) {
                return $filename;
            }
        }

        $name = $this->contentType->getParam('name');
        if ($name !== null) {
            return $name;
        }

        return null;
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

    public function getDecodedBody(ContentDecoder $decoder, CharsetConverter $charsetConverter): string
    {
        if ($this->decodedBody !== null) {
            return $this->decodedBody;
        }

        $content = $decoder->decode($this->rawBody, $this->getTransferEncoding());

        if ($this->getPrimaryType() === 'text') {
            $charset = $this->contentType->getParam('charset') ?? 'us-ascii';
            $content = $charsetConverter->toUtf8($content, $charset);
        }

        $this->decodedBody = $content;

        return $content;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getRawBodySize(): int
    {
        return strlen($this->rawBody);
    }
}
