<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit;

use PHPdot\Session\Exception\SessionException;
use PHPdot\Session\SessionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SessionIdTest extends TestCase
{
    #[Test]
    public function generateProduces64CharHexString(): void
    {
        $id = SessionId::generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id->toString());
    }

    #[Test]
    public function generateProducesUniqueValues(): void
    {
        $id1 = SessionId::generate();
        $id2 = SessionId::generate();

        self::assertNotSame($id1->toString(), $id2->toString());
    }

    #[Test]
    public function fromStringAcceptsValid64CharHex(): void
    {
        $hex = str_repeat('ab', 32);
        $id = SessionId::fromString($hex);

        self::assertSame($hex, $id->toString());
    }

    #[Test]
    public function fromStringRejectsEmptyString(): void
    {
        $this->expectException(SessionException::class);

        SessionId::fromString('');
    }

    #[Test]
    public function fromStringRejectsShortString(): void
    {
        $this->expectException(SessionException::class);

        SessionId::fromString('abcdef');
    }

    #[Test]
    public function fromStringRejectsTooLongString(): void
    {
        $this->expectException(SessionException::class);

        SessionId::fromString(str_repeat('a', 65));
    }

    #[Test]
    public function fromStringRejectsUppercaseHex(): void
    {
        $this->expectException(SessionException::class);

        SessionId::fromString(str_repeat('AB', 32));
    }

    #[Test]
    public function fromStringRejectsNonHexCharacters(): void
    {
        $this->expectException(SessionException::class);

        SessionId::fromString(str_repeat('zz', 32));
    }

    #[Test]
    public function equalsReturnsTrueForSameValue(): void
    {
        $hex = str_repeat('ab', 32);
        $id1 = SessionId::fromString($hex);
        $id2 = SessionId::fromString($hex);

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function equalsReturnsFalseForDifferentValues(): void
    {
        $id1 = SessionId::generate();
        $id2 = SessionId::generate();

        self::assertFalse($id1->equals($id2));
    }

    #[Test]
    public function toStringRoundTripsWithFromString(): void
    {
        $id1 = SessionId::generate();
        $id2 = SessionId::fromString($id1->toString());

        self::assertTrue($id1->equals($id2));
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(SessionId::class);

        self::assertTrue($reflection->isFinal());
    }
}
