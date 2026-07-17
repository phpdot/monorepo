<?php

declare(strict_types=1);

/**
 * Maps a {@see Message} onto a symfony/mime {@see Email}, applying the config's
 * default sender when the message sets none. Internal to the transport boundary
 * — one of only two places the package meets Symfony's type system.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Transport;

use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Message\Attachment;
use PHPdot\Mail\Message\Mailbox;
use PHPdot\Mail\Message\Message;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[Singleton]
final class EmailFactory
{
    /**
     * Builds a symfony/mime Email from the message, applying the config's default sender when none is set.
     *
     * @param Message $message
     * @param MailConfig $config
     *
     * @return Email
     */
    public function create(Message $message, MailConfig $config): Email
    {
        $email = new Email();

        $from = $message->sender();
        if ($from !== null) {
            $email->from($this->address($from));
        } elseif ($config->fromEmail !== '') {
            $email->from(new Address($config->fromEmail, $config->fromName));
        }

        if ($message->recipients() !== []) {
            $email->to(...$this->addresses($message->recipients()));
        }
        if ($message->carbonCopies() !== []) {
            $email->cc(...$this->addresses($message->carbonCopies()));
        }
        if ($message->blindCarbonCopies() !== []) {
            $email->bcc(...$this->addresses($message->blindCarbonCopies()));
        }
        if ($message->replyAddresses() !== []) {
            $email->replyTo(...$this->addresses($message->replyAddresses()));
        }

        if ($message->subjectLine() !== '') {
            $email->subject($message->subjectLine());
        }
        if ($message->textBody() !== null) {
            $email->text($message->textBody());
        }
        if ($message->htmlBody() !== null) {
            $email->html($message->htmlBody());
        }
        if ($message->priorityLevel() !== null) {
            $email->priority($message->priorityLevel());
        }

        foreach ($message->customHeaders() as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }
        foreach ($message->attachments() as $attachment) {
            $this->attach($email, $attachment);
        }

        return $email;
    }

    /**
     * Converts a list of mailboxes into symfony/mime addresses.
     *
     * @param list<Mailbox> $mailboxes
     *
     * @return list<Address>
     */
    private function addresses(array $mailboxes): array
    {
        return array_map(fn(Mailbox $mailbox): Address => $this->address($mailbox), $mailboxes);
    }

    /**
     * Converts a Mailbox into a symfony/mime Address.
     *
     * @param Mailbox $mailbox
     *
     * @return Address
     */
    private function address(Mailbox $mailbox): Address
    {
        return new Address($mailbox->email, $mailbox->name);
    }

    /**
     * Attaches a file (disk path or in-memory bytes) to the Symfony email.
     *
     * @param Email $email
     * @param Attachment $attachment
     *
     * @return void
     */
    private function attach(Email $email, Attachment $attachment): void
    {
        if ($attachment->path !== null) {
            $email->attachFromPath($attachment->path, $attachment->name, $attachment->contentType);
        } elseif ($attachment->body !== null) {
            $email->attach($attachment->body, $attachment->name, $attachment->contentType);
        }
    }
}
