<?php

declare(strict_types=1);

namespace PHPdot\Attribute\Tests\Unit\Enum;

use PHPdot\Attribute\Enum\StructureType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ValueError;

final class StructureTypeTest extends TestCase
{
    #[Test]
    public function allCasesExist(): void
    {
        $cases = StructureType::cases();

        self::assertCount(4, $cases);
    }

    #[Test]
    public function caseValues(): void
    {
        self::assertSame('class', StructureType::CLASS_TYPE->value);
        self::assertSame('enum', StructureType::ENUM_TYPE->value);
        self::assertSame('interface', StructureType::INTERFACE_TYPE->value);
        self::assertSame('trait', StructureType::TRAIT_TYPE->value);
    }

    #[Test]
    public function fromValidValues(): void
    {
        self::assertSame(StructureType::CLASS_TYPE, StructureType::from('class'));
        self::assertSame(StructureType::ENUM_TYPE, StructureType::from('enum'));
        self::assertSame(StructureType::INTERFACE_TYPE, StructureType::from('interface'));
        self::assertSame(StructureType::TRAIT_TYPE, StructureType::from('trait'));
    }

    #[Test]
    public function fromInvalidValueThrows(): void
    {
        $this->expectException(ValueError::class);
        StructureType::from('invalid');
    }

    #[Test]
    public function tryFromInvalidValueReturnsNull(): void
    {
        self::assertNull(StructureType::tryFrom('invalid'));
    }
}
