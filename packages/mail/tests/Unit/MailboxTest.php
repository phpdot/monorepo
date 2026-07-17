<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Exception\MailException;
use PHPdot\Mail\Message\Mailbox;
use PHPUnit\Framework\TestCase;

final class MailboxTest extends TestCase
{
    public function testHoldsEmailAndName(): void
    {
        $mailbox = new Mailbox('alice@example.com', 'Alice');

        self::assertSame('alice@example.com', $mailbox->email);
        self::assertSame('Alice', $mailbox->name);
    }

    public function testNameDefaultsToEmpty(): void
    {
        self::assertSame('', (new Mailbox('alice@example.com'))->name);
    }

    public function testRejectsAddressWithoutAtSign(): void
    {
        $this->expectException(MailException::class);

        new Mailbox('not-an-email');
    }

    public function testRejectsAddressWithSpace(): void
    {
        $this->expectException(MailException::class);

        new Mailbox('alice @example.com');
    }
}
