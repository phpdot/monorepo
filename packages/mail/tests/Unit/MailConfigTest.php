<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\MailConfig;
use PHPUnit\Framework\TestCase;

final class MailConfigTest extends TestCase
{
    public function testDefaultsToTheNullTransport(): void
    {
        $config = new MailConfig();

        self::assertSame('null://null', $config->dsn);
        self::assertSame('', $config->fromEmail);
        self::assertSame('', $config->fromName);
    }

    public function testKeepsProvidedValues(): void
    {
        $config = new MailConfig(
            dsn: 'smtp://localhost:1025',
            fromEmail: 'no-reply@example.com',
            fromName: 'Example',
        );

        self::assertSame('smtp://localhost:1025', $config->dsn);
        self::assertSame('no-reply@example.com', $config->fromEmail);
        self::assertSame('Example', $config->fromName);
    }

    public function testRejectsEmptyDsn(): void
    {
        $this->expectException(MailException::class);

        new MailConfig(dsn: '');
    }
}
