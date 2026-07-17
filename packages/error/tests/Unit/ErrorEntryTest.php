<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Error\ErrorEntry;
use PHPdot\Error\ErrorType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorEntryTest extends TestCase
{
    #[Test]
    public function it_stores_all_fields(): void
    {
        $entry = new ErrorEntry(
            code: '00010001',
            message: 'User not found',
            description: 'errors.user.not_found',
            type: ErrorType::NOT_FOUND,
            httpStatus: 404,
            context: 'user_id',
            params: ['id' => 42],
        );

        self::assertSame('00010001', $entry->code);
        self::assertSame('User not found', $entry->message);
        self::assertSame('errors.user.not_found', $entry->description);
        self::assertSame(ErrorType::NOT_FOUND, $entry->type);
        self::assertSame(404, $entry->httpStatus);
        self::assertSame('user_id', $entry->context);
        self::assertSame(['id' => 42], $entry->params);
    }

    #[Test]
    public function it_has_sensible_defaults(): void
    {
        $entry = new ErrorEntry(
            code: '00010001',
            message: 'Error',
            description: 'errors.generic',
            type: ErrorType::SERVER,
            httpStatus: 500,
        );

        self::assertNull($entry->context);
        self::assertSame([], $entry->params);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $entry = new ErrorEntry(
            code: '00010002',
            message: 'Email taken',
            description: 'errors.user.email_taken',
            type: ErrorType::CONFLICT,
            httpStatus: 409,
            context: 'email',
            params: ['email' => 'test@example.com'],
        );

        $array = $entry->toArray();

        self::assertSame('00010002', $array['code']);
        self::assertSame('Email taken', $array['message']);
        self::assertSame('errors.user.email_taken', $array['description']);
        self::assertSame('conflict', $array['type']);
        self::assertSame(409, $array['httpStatus']);
        self::assertSame('email', $array['context']);
        self::assertSame(['email' => 'test@example.com'], $array['params']);
    }

    #[Test]
    public function it_converts_to_array_with_null_context(): void
    {
        $entry = new ErrorEntry(
            code: '00010001',
            message: 'Error',
            description: 'errors.generic',
            type: ErrorType::SERVER,
            httpStatus: 500,
        );

        $array = $entry->toArray();
        self::assertNull($array['context']);
        self::assertSame([], $array['params']);
    }

    #[Test]
    public function it_is_readonly(): void
    {
        $reflection = new \ReflectionClass(ErrorEntry::class);
        self::assertTrue($reflection->isReadOnly());
        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function it_converts_error_type_to_string_in_array(): void
    {
        $entry = new ErrorEntry('c', 'm', 'd', ErrorType::VALIDATION, 422);

        self::assertSame('validation', $entry->toArray()['type']);
    }

    #[Test]
    public function it_stores_complex_params(): void
    {
        $entry = new ErrorEntry(
            code: 'c',
            message: 'm',
            description: 'd',
            type: ErrorType::VALIDATION,
            httpStatus: 422,
            params: [
                'min' => 8,
                'max' => 100,
                'allowed' => ['a', 'b', 'c'],
                'nested' => ['key' => 'value'],
            ],
        );

        self::assertSame(8, $entry->params['min']);
        self::assertSame(['a', 'b', 'c'], $entry->params['allowed']);
    }
}
