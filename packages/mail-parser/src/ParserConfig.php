<?php

declare(strict_types=1);

/**
 * Configuration value object for controlling parser behavior, security limits, and output options.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser;

final readonly class ParserConfig
{
    public function __construct(
        /** Maximum multipart nesting depth. */
        public int $maxNestingDepth = 50,
        /** Maximum total header block size in bytes (2 MiB). */
        public int $maxHeaderSize = 2_097_152,
        /** Maximum number of headers per block (0 = unlimited). */
        public int $maxHeaderCount = 1000,
        /** Maximum individual attachment size in bytes (50 MiB). 0 = unlimited. */
        public int $maxAttachmentSize = 52_428_800,
        /** Maximum total message size in bytes (100 MiB). 0 = unlimited. */
        public int $maxMessageSize = 104_857_600,
        /** Strict mode throws on RFC violations; lenient mode does best-effort parsing. */
        public bool $strict = false,
        /** How to return attachment content: 'string', 'base64', or 'none'. */
        public string $attachmentEncoding = 'string',
        /** Whether to apply format=flowed processing to text/plain bodies. */
        public bool $decodeFlowed = true,
    ) {}
}
