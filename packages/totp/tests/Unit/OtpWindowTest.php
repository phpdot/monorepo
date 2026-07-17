<?php

declare(strict_types=1);

namespace PHPdot\Totp\Tests\Unit;

use PHPdot\Totp\Exception\OtpException;
use PHPdot\Totp\Result\OtpWindow;
use PHPUnit\Framework\TestCase;

final class OtpWindowTest extends TestCase
{
    public function test_exposes_previous_current_next(): void
    {
        $window = new OtpWindow([9 => 'aaaaaa', 10 => 'bbbbbb', 11 => 'cccccc'], 10);

        self::assertSame('aaaaaa', $window->previous());
        self::assertSame('bbbbbb', $window->current());
        self::assertSame('cccccc', $window->next());
        self::assertSame(['aaaaaa', 'bbbbbb', 'cccccc'], $window->all());
    }

    public function test_edges_are_null_when_window_does_not_reach(): void
    {
        $window = new OtpWindow([10 => 'bbbbbb'], 10);

        self::assertNull($window->previous());
        self::assertNull($window->next());
        self::assertSame('bbbbbb', $window->current());
    }

    public function test_missing_current_throws(): void
    {
        $window = new OtpWindow([9 => 'aaaaaa'], 10);

        $this->expectException(OtpException::class);

        $window->current();
    }
}
