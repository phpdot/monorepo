<?php

declare(strict_types=1);

/**
 * The Transport boundary translates every Symfony failure into the package
 * hierarchy — no Symfony type leaks past it. Only construction-time failures
 * are exercised here so the suite stays hermetic: an unsupported DSN scheme
 * and a malformed sendmail command both fail before any I/O happens.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Tests\Unit;

use InvalidArgumentException;
use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Exception\TransportException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\TestCase;

final class TransportTranslationTest extends TestCase
{
    private function mailer(string $dsn): Mailer
    {
        return new Mailer(new MailConfig(dsn: $dsn), new Transport(new EmailFactory()));
    }

    private function deliverable(Mailer $mailer): void
    {
        $mailer->message()
            ->from('no-reply@example.com')
            ->to('alice@example.com')
            ->subject('x')
            ->text('x')
            ->send();
    }

    public function testAnUnsupportedDsnSchemeSurfacesAsMailException(): void
    {
        try {
            $this->deliverable($this->mailer('carrier-pigeon://loft'));
            self::fail('an unsupported DSN scheme must throw');
        } catch (MailException $e) {
            self::assertNotInstanceOf(TransportException::class, $e);
            self::assertStringContainsString('carrier-pigeon', $e->getMessage());
            self::assertNotNull($e->getPrevious());
        }
    }

    public function testAMalformedSendmailCommandSurfacesAsMailException(): void
    {
        try {
            $this->deliverable($this->mailer('sendmail://default?command=/bin/cat'));
            self::fail('a malformed sendmail command must throw');
        } catch (MailException $e) {
            self::assertNotInstanceOf(TransportException::class, $e);
            self::assertStringContainsString('sendmail command', $e->getMessage());
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
    }
}
