<?php

declare(strict_types=1);

/**
 * Immutable result object representing a fully parsed email with structured access to headers,
 * addresses, bodies, attachments, and authentication data.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Parser\Result;

use DateTimeImmutable;
use PHPdot\Mail\Parser\Header\Header;
use PHPdot\Mail\Parser\Header\HeaderCollection;

final readonly class Email
{
    /**
     * @param list<ReceivedHop> $received
     * @param list<DkimSignature> $dkim
     * @param list<AuthResult> $authResults
     * @param list<string> $references
     * @param list<BodyPart> $textBody the parts to show in order, preferring plain text (RFC 8621 §4.1.4)
     * @param list<BodyPart> $htmlBody the parts to show in order, preferring HTML (RFC 8621 §4.1.4)
     */
    public function __construct(
        public string|null $subject,
        public string|null $messageId,
        public DateTimeImmutable|null $date,
        public Address|null $from,
        public AddressList $fromAll,
        public Address|null $sender,
        public AddressList $to,
        public AddressList $cc,
        public AddressList $bcc,
        public AddressList $replyTo,
        public string|null $inReplyTo,
        public array $references,
        public string|null $text,
        public string|null $html,
        public string|null $calendar,
        public AttachmentList $attachments,
        public array $received,
        public array $dkim,
        public SpfResult|null $spf,
        public array $authResults,
        private HeaderCollection $headerCollection,
        public array $textBody = [],
        public array $htmlBody = [],
    ) {}

    /**
     * How many From fields the message carries; RFC 5322 §3.6 allows one, and a message with more names no one sender.
     */
    public function fromFieldCount(): int
    {
        return count($this->headerCollection->getAll('From'));
    }

    /**
     * Get the decoded value of the first header matching the given name, or null.
     */
    public function header(string $name): string|null
    {
        return $this->headerCollection->get($name)?->value;
    }

    /**
     * Get all decoded values for headers matching the given name.
     *
     * @return list<string>
     */
    public function headerAll(string $name): array
    {
        return array_map(
            fn(Header $h) => $h->value,
            $this->headerCollection->getAll($name),
        );
    }

    /**
     * Check whether a header with the given name exists.
     */
    public function hasHeader(string $name): bool
    {
        return $this->headerCollection->has($name);
    }
}
