<?php

declare(strict_types=1);

/**
 * An attachment of a parsed message: its type, name and decoded size, and its content held in memory, decoded from
 * the message when read, or not kept, as the parser's content storage chose.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use Closure;
use PHPdot\Mail\Parser\Header\HeaderCollection;

final class Attachment
{
    /** @var resource|null */
    private $stream = null;

    /**
     * @param string|(Closure(): (resource|null))|null $content the decoded bytes, or what opens them as a stream
     */
    public function __construct(
        public readonly string $mimeType,
        public readonly string|null $filename,
        public readonly string $disposition,
        public readonly int $size,
        public readonly string|null $contentId = null,
        public readonly HeaderCollection|null $headers = null,
        public readonly Email|null $nestedEmail = null,
        private readonly string|Closure|null $content = null,
    ) {}

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Whether the content can be read: false when storage kept none or the attachment was over its size limit.
     */
    public function hasContent(): bool
    {
        return $this->content !== null;
    }

    /**
     * The decoded bytes, read whole; for a large attachment kept in its source, `stream()` reads them without that.
     */
    public function content(): string|null
    {
        if (!$this->content instanceof Closure) {
            return $this->content;
        }

        $stream = $this->stream();

        if ($stream === null) {
            return null;
        }

        $bytes = stream_get_contents($stream);
        rewind($stream);

        return $bytes === false ? null : $bytes;
    }

    /**
     * The decoded bytes as a readable stream rewound to its start, to copy to a file or bucket without holding them.
     *
     * @return resource|null
     */
    public function stream()
    {
        if (is_resource($this->stream)) {
            rewind($this->stream);

            return $this->stream;
        }

        if ($this->content === null) {
            return null;
        }

        if ($this->content instanceof Closure) {
            $this->stream = ($this->content)();

            return $this->stream;
        }

        $stream = fopen('php://temp/maxmemory:2097152', 'w+b');

        if ($stream === false) {
            return null;
        }

        fwrite($stream, $this->content);
        rewind($stream);
        $this->stream = $stream;

        return $stream;
    }
}
