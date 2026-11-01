<?php

declare(strict_types=1);

/**
 * A part shown as the message body (RFC 8621 §4.1.4): its decoded UTF-8 text when it is text, and the part itself —
 * name, size and content, kept as the parser's storage chose — for any type, an inline image as much as HTML.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

final readonly class BodyPart
{
    public function __construct(
        public string $mimeType,
        public string|null $text,
        public Attachment $part,
    ) {}
}
