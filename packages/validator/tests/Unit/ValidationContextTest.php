<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationContextTest extends TestCase
{
    #[Test]
    public function fieldReturnsFieldName(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertSame('email', $context->field());
    }

    #[Test]
    public function valueReturnsFieldValue(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertSame('a@b.com', $context->value('email'));
    }

    #[Test]
    public function valueReturnsDefaultForMissingField(): void
    {
        $context = new ValidationContext('email', []);

        self::assertNull($context->value('email'));
        self::assertSame('fallback', $context->value('email', 'fallback'));
    }

    #[Test]
    public function valueSupportsDotNotation(): void
    {
        $context = new ValidationContext('city', [
            'address' => ['city' => 'Cairo', 'zip' => '11511'],
        ]);

        self::assertSame('Cairo', $context->value('address.city'));
        self::assertSame('11511', $context->value('address.zip'));
        self::assertNull($context->value('address.country'));
    }

    #[Test]
    public function valueReturnsDefaultWhenDotPathTraversesNonArray(): void
    {
        $context = new ValidationContext('name', ['name' => 'Omar']);

        self::assertSame('default', $context->value('name.first', 'default'));
    }

    #[Test]
    public function hasReturnsTrueForExistingField(): void
    {
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertTrue($context->has('email'));
    }

    #[Test]
    public function hasReturnsFalseForMissingField(): void
    {
        $context = new ValidationContext('email', []);

        self::assertFalse($context->has('email'));
    }

    #[Test]
    public function hasSupportsDotNotation(): void
    {
        $context = new ValidationContext('city', [
            'address' => ['city' => 'Cairo'],
        ]);

        self::assertTrue($context->has('address.city'));
        self::assertFalse($context->has('address.country'));
    }

    #[Test]
    public function allReturnsFullPayload(): void
    {
        $data = ['email' => 'a@b.com', 'age' => 30];
        $context = new ValidationContext('email', $data);

        self::assertSame($data, $context->all());
    }

    #[Test]
    public function nullFieldValueIsReturnedAsNull(): void
    {
        $context = new ValidationContext('middle_name', ['middle_name' => null]);

        self::assertNull($context->value('middle_name'));
        self::assertTrue($context->has('middle_name'));
    }
}
