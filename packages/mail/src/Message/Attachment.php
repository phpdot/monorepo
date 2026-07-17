<?php

declare(strict_types=1);

/**
 * A file attached to a message — either a path on disk or raw bytes in memory.
 * Created through the named constructors; the transport turns it into a MIME part.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Message;

final readonly class Attachment
{
    /**
     * Holds an attachment as either a disk path or in-memory bytes.
     *
     * @param ?string $path
     * @param ?string $body
     * @param ?string $name
     * @param ?string $contentType
     */
    private function __construct(
        public ?string $path,
        public ?string $body,
        public ?string $name,
        public ?string $contentType,
    ) {}

    /**
     * Attach a file from disk. The name defaults to the file's basename.
     *
     * @param string $path
     * @param ?string $name
     * @param ?string $contentType
     *
     * @return self
     */
    public static function fromPath(string $path, ?string $name = null, ?string $contentType = null): self
    {
        return new self($path, null, $name, $contentType);
    }

    /**
     * Attach raw bytes already in memory under the given file name.
     *
     * @param string $body
     * @param string $name
     * @param ?string $contentType
     *
     * @return Attachment
     */
    public static function fromData(string $body, string $name, ?string $contentType = null): self
    {
        return new self(null, $body, $name, $contentType);
    }
}
