<?php

declare(strict_types=1);

/**
 * The one entry point a developer injects: compose a message and send it.
 * Stateless — every `send()` builds its own transport, so a single shared
 * instance is safe across coroutines under Swoole. Compose on the {@see Message}
 * returned by `message()`, or start the chain from one of the shortcuts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail;

use PHPdot\Container\Attribute\Binds;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mail\Contract\MailerInterface;
use PHPdot\Mail\Message\Message;
use PHPdot\Mail\Transport\Transport;

#[Singleton]
#[Binds(MailerInterface::class)]
final class Mailer implements MailerInterface
{
    /**
     * Creates the mailer over its configuration and transport.
     *
     * @param MailConfig $config
     * @param Transport $transport
     */
    public function __construct(
        private readonly MailConfig $config,
        private readonly Transport $transport,
    ) {}

    public function message(): Message
    {
        return new Message(fn(Message $message): Receipt => $this->transport->send($message, $this->config));
    }

    public function from(string $email, string $name = ''): Message
    {
        return $this->message()->from($email, $name);
    }

    public function to(string $email, string $name = ''): Message
    {
        return $this->message()->to($email, $name);
    }

    public function cc(string $email, string $name = ''): Message
    {
        return $this->message()->cc($email, $name);
    }

    public function bcc(string $email, string $name = ''): Message
    {
        return $this->message()->bcc($email, $name);
    }

    public function replyTo(string $email, string $name = ''): Message
    {
        return $this->message()->replyTo($email, $name);
    }

    public function subject(string $subject): Message
    {
        return $this->message()->subject($subject);
    }

    public function html(string $html): Message
    {
        return $this->message()->html($html);
    }

    public function text(string $text): Message
    {
        return $this->message()->text($text);
    }
}
