<?php

declare(strict_types=1);

/**
 * Stack frame for tracking nested MIME multipart boundaries during streaming parsing.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Streaming;

use PHPdot\Mail\Parser\Header\HeaderCollection;
use PHPdot\Mail\Parser\Mime\MimeNode;
use PHPdot\Mail\Parser\Result\MimeHeader;

final class BoundaryFrame
{
    /** @var list<MimeNode> */
    public array $children = [];

    public function __construct(
        public readonly string $boundary,
        public readonly MimeHeader $contentType,
        public readonly HeaderCollection $headers,
    ) {}
}
