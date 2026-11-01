<?php

declare(strict_types=1);

/**
 * AWS SES delivery transport using sendRawEmail API.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Gateway\Delivery;

use PHPdot\Mail\Gateway\Exception\DeliveryException;

/**
 * Delivers messages via AWS SES SendRawEmail API.
 *
 * Uses AWS Signature V4 — no AWS SDK dependency required.
 * Alternatively, inject an SesClientInterface to use the AWS SDK.
 */
final class SesTransport implements TransportInterface
{
    public function __construct(
        private readonly SesClientInterface $client,
    ) {}

    public function send(string $eml, string $sender, array $recipients): string
    {
        try {
            return $this->client->sendRawEmail($eml, $sender, $recipients);
        } catch (\Throwable $e) {
            throw new DeliveryException('SES delivery failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function name(): string
    {
        return 'ses';
    }
}
