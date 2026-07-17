<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class ValidationContextTest extends TestCase
{
    public function test_field_returns_field_name(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertSame('email', $context->field());
    }

    public function test_value_returns_field_value(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertSame('a@b.com', $context->value('email'));
    }

    public function test_value_returns_default_for_missing_field(): void
    {
        $context = new ValidationContext('email', []);

        self::assertNull($context->value('email'));
        self::assertSame('fallback', $context->value('email', 'fallback'));
    }

    public function test_value_supports_dot_notation(): void
    {
        $context = new ValidationContext('city', [
            'address' => ['city' => 'Cairo', 'zip' => '11511'],
        ]);

        self::assertSame('Cairo', $context->value('address.city'));
        self::assertSame('11511', $context->value('address.zip'));
        self::assertNull($context->value('address.country'));
    }

    public function test_value_returns_default_when_dot_path_traverses_non_array(): void
    {
        $context = new ValidationContext('name', ['name' => 'Omar']);

        self::assertSame('default', $context->value('name.first', 'default'));
    }

    public function test_has_returns_true_for_existing_field(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertTrue($context->has('email'));
    }

    public function test_has_returns_false_for_missing_field(): void
    {
        $context = new ValidationContext('email', []);

        self::assertFalse($context->has('email'));
    }

    public function test_has_supports_dot_notation(): void
    {
        $context = new ValidationContext('city', [
            'address' => ['city' => 'Cairo'],
        ]);

        self::assertTrue($context->has('address.city'));
        self::assertFalse($context->has('address.country'));
    }

    public function test_all_returns_full_payload(): void
    {
        $data = ['email' => 'a@b.com', 'age' => 30];
        $context = new ValidationContext('email', $data);

        self::assertSame($data, $context->all());
    }

    public function test_null_field_value_is_returned_as_null(): void
    {
        $context = new ValidationContext('middle_name', ['middle_name' => null]);

        self::assertNull($context->value('middle_name'));
        self::assertTrue($context->has('middle_name'));
    }
}
