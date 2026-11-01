<?php

declare(strict_types=1);

/**
 * Thin abstraction over AWS SES API.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Delivery;

/**
 * Minimal SES client interface.
 *
 * Implement this to bridge to your preferred SDK:
 * - aws/aws-sdk-php (SesClient::sendRawEmail)
 * - async-aws/ses
 * - Or a direct HTTP implementation with AWS Signature V4
 */
interface SesClientInterface
{
    /**
     * Send a raw email via SES.
     *
     * @param string $rawMessage Raw RFC 5322 message
     * @param string $sender Envelope sender
     * @param list<string> $recipients Envelope recipients
     *
     * @return string SES message ID
     */
    public function sendRawEmail(string $rawMessage, string $sender, array $recipients): string;
}
