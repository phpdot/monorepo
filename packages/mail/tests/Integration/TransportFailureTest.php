<?php

declare(strict_types=1);

/**
 * Wire-level failure translation against a port nothing listens on: the
 * loopback connection is refused before any traffic leaves the machine, so
 * the case is deterministic without needing a service from the compose stack.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mail\Tests\Integration;

use PHPdot\Mail\Exception\TransportException;
use PHPdot\Mail\MailConfig;
use PHPdot\Mail\Mailer;
use PHPdot\Mail\Transport\EmailFactory;
use PHPdot\Mail\Transport\Transport;
use PHPUnit\Framework\TestCase;

final class TransportFailureTest extends TestCase
{
    public function testAnUnreachableSmtpEndpointSurfacesAsTransportException(): void
    {
        $mailer = new Mailer(new MailConfig(dsn: 'smtp://127.0.0.1:1'), new Transport(new EmailFactory()));

        try {
            $mailer->from('no-reply@example.com')->to('alice@example.com')->subject('x')->text('x')->send();
            self::fail('an unreachable SMTP endpoint must throw');
        } catch (TransportException $e) {
            self::assertSame('smtp', $e->getScheme());
            self::assertNotNull($e->getPrevious());
        }
    }
}
