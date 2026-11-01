<?php

declare(strict_types=1);

/**
 * Configuration value object for controlling parser behavior, security limits, and output options.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser;

use PHPdot\Mail\Parser\Content\ContentStorage;

final readonly class ParserConfig
{
    public function __construct(
        /** Maximum nesting depth of MIME entities, multipart and message/rfc822 alike. */
        public int $maxNestingDepth = 50,
        /** Maximum size of one header block in bytes (1 MiB, as mailsplit). */
        public int $maxHeaderSize = 1_048_576,
        /** Maximum bytes of headers in a message, every part's and nested message's included (1 MiB, as mime4j; 0 = unlimited). */
        public int $maxTotalHeaderSize = 1_048_576,
        /** Maximum number of headers per block (0 = unlimited). */
        public int $maxHeaderCount = 1000,
        /** Maximum nesting of base64 or quoted-printable forwarded messages that are parsed, as Stalwart's (default 3). */
        public int $maxEncodedNesting = 3,
        /** Maximum number of MIME parts in a message, nested messages included (0 = unlimited). */
        public int $maxPartCount = 512,
        /** Maximum number of headers in a message, every part's and nested message's included (0 = unlimited). */
        public int $maxTotalHeaderCount = 16_384,
        /** Maximum individual attachment size in bytes (50 MiB). 0 = unlimited. */
        public int $maxAttachmentSize = 52_428_800,
        /** Maximum total message size in bytes (100 MiB). 0 = unlimited. */
        public int $maxMessageSize = 104_857_600,
        /** Strict mode throws on RFC violations; lenient mode does best-effort parsing. */
        public bool $strict = false,
        /** Where attachment content lives: decoded in memory, decoded from the message when read, or not kept. */
        public ContentStorage $storage = ContentStorage::Memory,
        /** Whether to apply format=flowed processing to text/plain bodies. */
        public bool $decodeFlowed = true,
    ) {}
}
