<?php

declare(strict_types=1);

/**
 * An email under construction. Immutable — every setter returns a new message,
 * so a configured base (shared sender, headers) is a safe reusable template, and
 * a chain off a shared Mailer never mutates it. The reader methods are how the
 * transport boundary inspects it.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Message;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Receipt;

final class Message
{
    private ?Mailbox $from = null;

    /**
     * @var list<Mailbox>
     */

    private array $to = [];

    /**
     * @var list<Mailbox>
     */

    private array $cc = [];

    /**
     * @var list<Mailbox>
     */

    private array $bcc = [];

    /**
     * @var list<Mailbox>
     */

    private array $replyTo = [];

    private string $subject = '';
    private ?string $html = null;
    private ?string $text = null;

    /**
     * @var list<Attachment>
     */

    private array $attachments = [];

    private ?int $priority = null;

    /**
     * @var array<string, string>
     */

    private array $headers = [];

    /**
     * Creates a message, optionally bound to the Mailer that will deliver it. A
     * stand-alone `new Message()` passes null and is sent via Mailer::send().
     *
     * @param (\Closure(self): Receipt)|null $dispatch Delivery closure, or null.
     */
    public function __construct(
        private readonly ?\Closure $dispatch = null,
    ) {}

    /**
     * Returns a copy of the message with the sender ("From") set.
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function from(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->from = new Mailbox($email, $name);

        return $clone;
    }

    /**
     * Returns a copy of the message with a "To" recipient appended.
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function to(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->to[] = new Mailbox($email, $name);

        return $clone;
    }

    /**
     * Returns a copy of the message with a carbon-copy ("Cc") recipient appended.
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function cc(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->cc[] = new Mailbox($email, $name);

        return $clone;
    }

    /**
     * Returns a copy of the message with a blind-carbon-copy ("Bcc") recipient appended.
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function bcc(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->bcc[] = new Mailbox($email, $name);

        return $clone;
    }

    /**
     * Returns a copy of the message with a "Reply-To" address appended.
     *
     * @param string $email
     * @param string $name
     *
     * @return self
     */
    public function replyTo(string $email, string $name = ''): self
    {
        $clone = clone $this;
        $clone->replyTo[] = new Mailbox($email, $name);

        return $clone;
    }

    /**
     * Returns a copy of the message with the subject line set.
     *
     * @param string $subject
     *
     * @return self
     */
    public function subject(string $subject): self
    {
        $clone = clone $this;
        $clone->subject = $subject;

        return $clone;
    }

    /**
     * Returns a copy of the message with the HTML body set.
     *
     * @param string $html
     *
     * @return self
     */
    public function html(string $html): self
    {
        $clone = clone $this;
        $clone->html = $html;

        return $clone;
    }

    /**
     * Returns a copy of the message with the plain-text body set.
     *
     * @param string $text
     *
     * @return self
     */
    public function text(string $text): self
    {
        $clone = clone $this;
        $clone->text = $text;

        return $clone;
    }

    /**
     * Attach a file from disk; the name defaults to its basename.
     *
     * @param string $path
     * @param ?string $name
     *
     * @return Message
     */
    public function attach(string $path, ?string $name = null): self
    {
        $clone = clone $this;
        $clone->attachments[] = Attachment::fromPath($path, $name);

        return $clone;
    }

    /**
     * Attach raw bytes already in memory under the given file name.
     *
     * @param string $body
     * @param ?string $contentType
     * @param string $name
     *
     * @return Message
     */
    public function attachData(string $body, string $name, ?string $contentType = null): self
    {
        $clone = clone $this;
        $clone->attachments[] = Attachment::fromData($body, $name, $contentType);

        return $clone;
    }

    /**
     * Importance from 1 (highest) to 5 (lowest).
     *
     * @param int $priority
     *
     * @return Message
     */
    public function priority(int $priority): self
    {
        $clone = clone $this;
        $clone->priority = $priority;

        return $clone;
    }

    /**
     * Returns a copy of the message with a custom header set.
     *
     * @param string $name
     * @param string $value
     *
     * @return self
     */
    public function header(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    /**
     * Send this message — the tail of a `$mail->to(...)->subject(...)->send()`
     * chain. Available only on a message the Mailer started; a stand-alone
     * `new Message()` has no mailer, so deliver it via {@see \PHPdot\Mail\Mailer::send()}.
     *
     * @throws MailException when the message was not started from a Mailer
     *
     * @return Receipt
     */
    public function send(): Receipt
    {
        return ($this->dispatch ?? throw new MailException(
            'This message was not started from a Mailer; send it with Mailer::send().',
        ))($this);
    }

    /**
     * The sender ("From") mailbox, or null when none was set.
     *
     * @return ?Mailbox
     */
    public function sender(): ?Mailbox
    {
        return $this->from;
    }

    /**
     * The "To" recipients added to the message.
     *
     * @return list<Mailbox>
     */
    public function recipients(): array
    {
        return $this->to;
    }

    /**
     * The carbon-copy ("Cc") recipients added to the message.
     *
     * @return list<Mailbox>
     */
    public function carbonCopies(): array
    {
        return $this->cc;
    }

    /**
     * The blind-carbon-copy ("Bcc") recipients added to the message.
     *
     * @return list<Mailbox>
     */
    public function blindCarbonCopies(): array
    {
        return $this->bcc;
    }

    /**
     * The "Reply-To" addresses added to the message.
     *
     * @return list<Mailbox>
     */
    public function replyAddresses(): array
    {
        return $this->replyTo;
    }

    /**
     * The subject line set on the message.
     *
     * @return string
     */
    public function subjectLine(): string
    {
        return $this->subject;
    }

    /**
     * The HTML body, or null when none was set.
     *
     * @return ?string
     */
    public function htmlBody(): ?string
    {
        return $this->html;
    }

    /**
     * The plain-text body, or null when none was set.
     *
     * @return ?string
     */
    public function textBody(): ?string
    {
        return $this->text;
    }

    /**
     * The attachments added to the message.
     *
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return $this->attachments;
    }

    /**
     * The importance level (1 highest … 5 lowest), or null when unset.
     *
     * @return ?int
     */
    public function priorityLevel(): ?int
    {
        return $this->priority;
    }

    /**
     * The custom headers set on the message, keyed by header name.
     *
     * @return array<string, string>
     */
    public function customHeaders(): array
    {
        return $this->headers;
    }
}
