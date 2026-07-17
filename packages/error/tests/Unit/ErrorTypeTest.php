<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Error\ErrorType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorTypeTest extends TestCase
{
    #[Test]
    public function it_has_all_nine_types(): void
    {
        $cases = ErrorType::cases();
        self::assertCount(9, $cases);
    }

    #[Test]
    public function it_has_correct_values(): void
    {
        self::assertSame('validation', ErrorType::VALIDATION->value);
        self::assertSame('authentication', ErrorType::AUTHENTICATION->value);
        self::assertSame('authorization', ErrorType::AUTHORIZATION->value);
        self::assertSame('not_found', ErrorType::NOT_FOUND->value);
        self::assertSame('conflict', ErrorType::CONFLICT->value);
        self::assertSame('rate_limit', ErrorType::RATE_LIMIT->value);
        self::assertSame('timeout', ErrorType::TIMEOUT->value);
        self::assertSame('unavailable', ErrorType::UNAVAILABLE->value);
        self::assertSame('server', ErrorType::SERVER->value);
    }

    #[Test]
    public function it_creates_from_value(): void
    {
        self::assertSame(ErrorType::VALIDATION, ErrorType::from('validation'));
        self::assertSame(ErrorType::SERVER, ErrorType::from('server'));
    }

    #[Test]
    public function it_returns_null_for_invalid_value(): void
    {
        self::assertNull(ErrorType::tryFrom('nonexistent'));
    }
}
