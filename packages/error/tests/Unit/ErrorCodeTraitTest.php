<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Error\ErrorType;
use PHPdot\Error\Tests\Fixtures\UserErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorCodeTraitTest extends TestCase
{
    #[Test]
    public function it_returns_code_from_enum_value(): void
    {
        self::assertSame('00010001', UserErrors::NOT_FOUND->getCode());
        self::assertSame('00010002', UserErrors::EMAIL_TAKEN->getCode());
    }

    #[Test]
    public function it_returns_message(): void
    {
        self::assertSame('User not found', UserErrors::NOT_FOUND->getMessage());
        self::assertSame('Email is already taken', UserErrors::EMAIL_TAKEN->getMessage());
    }

    #[Test]
    public function it_returns_description(): void
    {
        self::assertSame('errors.user.not_found', UserErrors::NOT_FOUND->getDescription());
        self::assertSame('errors.user.email_taken', UserErrors::EMAIL_TAKEN->getDescription());
    }

    #[Test]
    public function it_returns_type(): void
    {
        self::assertSame(ErrorType::NOT_FOUND, UserErrors::NOT_FOUND->getType());
        self::assertSame(ErrorType::CONFLICT, UserErrors::EMAIL_TAKEN->getType());
        self::assertSame(ErrorType::VALIDATION, UserErrors::INVALID_EMAIL->getType());
        self::assertSame(ErrorType::AUTHORIZATION, UserErrors::LOCKED->getType());
    }

    #[Test]
    public function it_returns_http_status(): void
    {
        self::assertSame(404, UserErrors::NOT_FOUND->getHttpStatus());
        self::assertSame(409, UserErrors::EMAIL_TAKEN->getHttpStatus());
        self::assertSame(422, UserErrors::INVALID_EMAIL->getHttpStatus());
        self::assertSame(403, UserErrors::LOCKED->getHttpStatus());
    }

    #[Test]
    public function it_returns_details_array(): void
    {
        $details = UserErrors::NOT_FOUND->getDetails();

        self::assertSame('User not found', $details['message']);
        self::assertSame('errors.user.not_found', $details['description']);
        self::assertSame(ErrorType::NOT_FOUND, $details['type']);
        self::assertSame(404, $details['httpStatus']);
    }

    #[Test]
    public function it_returns_consistent_details_across_calls(): void
    {
        $first = UserErrors::EMAIL_TAKEN->getDetails();
        $second = UserErrors::EMAIL_TAKEN->getDetails();

        self::assertSame($first, $second);
    }

    #[Test]
    public function it_works_with_all_enum_cases(): void
    {
        foreach (UserErrors::cases() as $case) {
            $details = $case->getDetails();

            self::assertNotEmpty($case->getCode());
            self::assertNotEmpty($case->getMessage());
            self::assertNotEmpty($case->getDescription());
            self::assertInstanceOf(ErrorType::class, $case->getType());
            self::assertGreaterThanOrEqual(100, $case->getHttpStatus());
            self::assertLessThan(600, $case->getHttpStatus());

            self::assertSame($details['message'], $case->getMessage());
            self::assertSame($details['description'], $case->getDescription());
            self::assertSame($details['type'], $case->getType());
            self::assertSame($details['httpStatus'], $case->getHttpStatus());
        }
    }
}
