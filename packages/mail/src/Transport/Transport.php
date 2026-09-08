<?php

declare(strict_types=1);

/**
 * The boundary to symfony/mailer's delivery side. A fresh transport is built per
 * send, so each call owns its socket and concurrent coroutines never share a
 * connection (coroutine-safe under Swoole). Every Symfony failure is translated
 * into the package's own exceptions, so no Symfony type leaks past this class.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Transport;

use InvalidArgumentException;
use PHPdot\Container\Attribute\Singleton;
use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Exception\TransportException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Message\Message;
use PHPdot\Mail\Receipt;
use Symfony\Component\Mailer\Exception\ExceptionInterface as MailerExceptionInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport as SymfonyTransport;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeExceptionInterface;

#[Singleton]
final class Transport
{
    /**
     * Creates the transport over the email factory.
     *
     * @param EmailFactory $emails
     */
    public function __construct(
        private readonly EmailFactory $emails,
    ) {}

    /**
     * Deliver a message using the DSN from config, returning the transport's
     * receipt (message id + debug transcript) when it is accepted.
     *
     * @param Message $message
     * @param MailConfig $config
     *
     * @throws TransportException when the transport cannot deliver the message
     * @throws MailException when the message or DSN is malformed
     *
     * @return Receipt
     */
    public function send(Message $message, MailConfig $config): Receipt
    {
        try {
            $email = $this->emails->create($message, $config);
            $sent = SymfonyTransport::fromDsn($config->dsn)->send($email);

            return new Receipt($sent?->getMessageId() ?? '', $sent?->getDebug() ?? '');
        } catch (TransportExceptionInterface $e) {
            throw new TransportException($e->getMessage(), scheme: self::schemeOf($config->dsn), previous: $e);
        } catch (MailerExceptionInterface | MimeExceptionInterface | InvalidArgumentException $e) {
            throw new MailException($e->getMessage(), previous: $e);
        }
    }

    /**
     * The DSN's scheme — the transport family on the wire. Extracted because
     * the DSN itself carries credentials and must never reach an exception a
     * log will persist.
     *
     * @param string $dsn
     *
     * @return string
     */
    private static function schemeOf(string $dsn): string
    {
        $scheme = parse_url($dsn, PHP_URL_SCHEME);

        return is_string($scheme) ? $scheme : '';
    }
}
