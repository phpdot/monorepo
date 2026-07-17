<?php

declare(strict_types=1);

/**
 * The injectable entry point to the package: start a message and send it. Bound
 * to {@see \PHPdot\Mail\Mailer} as a container singleton, so a consumer can
 * depend on this contract rather than the concrete service.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Contract;

use PHPdot\Mail\Message\Message;

interface MailerInterface
{
    /**
     * Start composing a message. Chain builders on it and finish with `->send()`
     * — or start the chain directly from one of the shortcuts below, e.g.
     * `$mail->to('a@b.com')->subject('Hi')->html('<p>Hi</p>')->send()`.
     *
     * @return Message
     */
    public function message(): Message;

    /**
     * Starts a message with the sender ("From") set.
     *
     * @param string $email
     * @param string $name
     *
     * @return Message
     */
    public function from(string $email, string $name = ''): Message;

    /**
     * Starts a message addressed to the given recipient.
     *
     * @param string $email
     * @param string $name
     *
     * @return Message
     */
    public function to(string $email, string $name = ''): Message;

    /**
     * Starts a message with a carbon-copy ("Cc") recipient.
     *
     * @param string $email
     * @param string $name
     *
     * @return Message
     */
    public function cc(string $email, string $name = ''): Message;

    /**
     * Starts a message with a blind-carbon-copy ("Bcc") recipient.
     *
     * @param string $email
     * @param string $name
     *
     * @return Message
     */
    public function bcc(string $email, string $name = ''): Message;

    /**
     * Starts a message with a "Reply-To" address.
     *
     * @param string $email
     * @param string $name
     *
     * @return Message
     */
    public function replyTo(string $email, string $name = ''): Message;

    /**
     * Starts a message with the subject line set.
     *
     * @param string $subject
     *
     * @return Message
     */
    public function subject(string $subject): Message;

    /**
     * Starts a message with the HTML body set.
     *
     * @param string $html
     *
     * @return Message
     */
    public function html(string $html): Message;

    /**
     * Starts a message with the plain-text body set.
     *
     * @param string $text
     *
     * @return Message
     */
    public function text(string $text): Message;
}
