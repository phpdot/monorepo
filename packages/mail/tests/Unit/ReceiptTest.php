<?php

declare(strict_types=1);

namespace PHPdot\Mail\Tests\Unit;

use PHPdot\Mail\Receipt;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    public function testCarriesMessageIdAndDebug(): void
    {
        $receipt = new Receipt('<abc123@example.com>', 'SMTP transcript');

        self::assertSame('<abc123@example.com>', $receipt->messageId);
        self::assertSame('SMTP transcript', $receipt->debug);
    }

    public function testDebugDefaultsToEmpty(): void
    {
        self::assertSame('', (new Receipt('<abc123@example.com>'))->debug);
    }
}
