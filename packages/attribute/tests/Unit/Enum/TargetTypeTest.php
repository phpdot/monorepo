<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Enum;

use PHPdot\Attribute\Enum\TargetType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

final class TargetTypeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = TargetType::cases();

        self::assertCount(5, $cases);
    }

    #[Test]
    public function caseValues(): void
    {
        self::assertSame('class', TargetType::CLASS_TYPE->value);
        self::assertSame('constant', TargetType::CONSTANT->value);
        self::assertSame('method', TargetType::METHOD->value);
        self::assertSame('parameter', TargetType::PARAMETER->value);
        self::assertSame('property', TargetType::PROPERTY->value);
    }

    #[Test]
    public function fromValidValues(): void
    {
        self::assertSame(TargetType::CLASS_TYPE, TargetType::from('class'));
        self::assertSame(TargetType::CONSTANT, TargetType::from('constant'));
        self::assertSame(TargetType::METHOD, TargetType::from('method'));
        self::assertSame(TargetType::PARAMETER, TargetType::from('parameter'));
        self::assertSame(TargetType::PROPERTY, TargetType::from('property'));
    }

    #[Test]
    public function fromInvalidValueThrows(): void
    {
        $this->expectException(ValueError::class);
        TargetType::from('invalid');
    }

    #[Test]
    public function tryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(TargetType::tryFrom('invalid'));
    }
}
