<?php

declare(strict_types=1);

/**
 * Delivery transport interface for sending queued messages.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Delivery;

/**
 * Pluggable delivery transport.
 *
 * Implementations: SesTransport, SmtpRelayTransport.
 */
interface TransportInterface
{
    /**
     * Send a raw .eml message.
     *
     * @param string $eml Raw RFC 5322 message content
     * @param string $sender MAIL FROM address (envelope sender)
     * @param list<string> $recipients RCPT TO addresses (envelope recipients)
     *
     * @throws \PHPdot\Mail\Gateway\Exception\DeliveryException
     *
     * @return string Provider message ID (e.g. SES message ID)
     */
    public function send(string $eml, string $sender, array $recipients): string;

    /**
     * Transport name for logging.
     */
    public function name(): string;
}
