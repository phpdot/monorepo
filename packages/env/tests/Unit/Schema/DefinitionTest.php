<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit\Schema;

use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Schema\Definition;
use PHPdot\Env\Tests\Stubs\Status;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DefinitionTest extends TestCase
{
    #[Test]
    public function defaultValues(): void
    {
        $def = new Definition();

        self::assertSame(EnvType::STRING, $def->type);
        self::assertNull($def->enum);
        self::assertFalse($def->required);
        self::assertFalse($def->notEmpty);
        self::assertNull($def->default);
        self::assertNull($def->description);
        self::assertSame(',', $def->separator);
        self::assertNull($def->min);
        self::assertNull($def->max);
        self::assertNull($def->allowed);
        self::assertNull($def->pattern);
        self::assertFalse($def->sensitive);
    }

    #[Test]
    public function allConstructorParamsStoredCorrectly(): void
    {
        $def = new Definition(
            type: EnvType::ENUM,
            enum: Status::class,
            required: true,
            notEmpty: true,
            default: Status::ACTIVE,
            description: 'Account status',
            separator: '|',
            min: 1,
            max: 100,
            allowed: ['active', 'inactive'],
            pattern: '/^[a-z]+$/',
            sensitive: true,
        );

        self::assertSame(EnvType::ENUM, $def->type);
        self::assertSame(Status::class, $def->enum);
        self::assertTrue($def->required);
        self::assertTrue($def->notEmpty);
        self::assertSame(Status::ACTIVE, $def->default);
        self::assertSame('Account status', $def->description);
        self::assertSame('|', $def->separator);
        self::assertSame(1, $def->min);
        self::assertSame(100, $def->max);
        self::assertSame(['active', 'inactive'], $def->allowed);
        self::assertSame('/^[a-z]+$/', $def->pattern);
        self::assertTrue($def->sensitive);
    }

    #[Test]
    public function readonlyImmutability(): void
    {
        $def = new Definition(type: EnvType::INT, default: 42);

        $reflection = new \ReflectionClass($def);
        self::assertTrue($reflection->isReadOnly());
    }
}
