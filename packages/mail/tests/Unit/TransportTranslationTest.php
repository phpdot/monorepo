<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Exception\TransportException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The Transport boundary translates every Symfony failure into the package
 * hierarchy — no Symfony type leaks past it. Exercised without a network:
 * an unsupported DSN scheme fails at transport construction, and a closed
 * loopback port is refused before any traffic leaves the machine.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class TransportTranslationTest extends TestCase
{
    private function mailer(string $dsn): Mailer
    {
        return new Mailer(new MailConfig(dsn: $dsn), new Transport(new EmailFactory()));
    }

    #[Test]
    public function unsupportedDsnSchemeSurfacesAsMailException(): void
    {
        $message = $this->mailer('carrier-pigeon://loft')->message()
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('x')
            ->text('x');

        try {
            $message->send();
            self::fail('an unsupported DSN scheme must throw');
        } catch (MailException $e) {
            self::assertNotInstanceOf(TransportException::class, $e);
            self::assertNotNull($e->getPrevious());
        }
    }

    #[Test]
    public function unreachableSmtpServerSurfacesAsTransportException(): void
    {
        $message = $this->mailer('smtp://127.0.0.1:1')->message()
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('x')
            ->text('x');

        $this->expectException(TransportException::class);

        $message->send();
    }

    #[Test]
    public function mailerSendDeliversAStandaloneMessage(): void
    {
        $mailer = $this->mailer('null://null');
        $message = (new \PHPdot\Mail\Message\Message())
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('standalone')
            ->text('sent without a mailer-started chain');

        $receipt = $mailer->send($message);

        self::assertNotSame('', $receipt->messageId);
    }
}
