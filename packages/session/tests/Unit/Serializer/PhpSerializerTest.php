<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Serializer;

use PHPdot\Session\Exception\SessionException;
use PHPdot\Session\Serializer\PhpSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PhpSerializerTest extends TestCase
{
    private PhpSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new PhpSerializer();
    }

    #[Test]
    public function encodeDecodeRoundTrip(): void
    {
        $data = ['key' => 'value', 'num' => 42, 'nested' => ['a' => 1]];

        $encoded = $this->serializer->encode($data);
        $decoded = $this->serializer->decode($encoded);

        self::assertSame($data, $decoded);
    }

    #[Test]
    public function decodeEmptyStringReturnsEmptyArray(): void
    {
        self::assertSame([], $this->serializer->decode(''));
    }

    #[Test]
    public function decodeInvalidDataThrows(): void
    {
        $this->expectException(SessionException::class);

        $this->serializer->decode('not valid serialized data');
    }

    #[Test]
    public function blocksObjectInjection(): void
    {
        $payload = serialize(['evil' => new \stdClass()]);

        $decoded = $this->serializer->decode($payload);

        // Objects are converted to __PHP_Incomplete_Class, preventing constructor execution
        self::assertArrayHasKey('evil', $decoded);
        self::assertInstanceOf(\__PHP_Incomplete_Class::class, $decoded['evil']);
    }

    #[Test]
    public function nestedArraysPreserved(): void
    {
        $data = ['a' => ['b' => ['c' => 'deep']]];

        $decoded = $this->serializer->decode($this->serializer->encode($data));

        self::assertSame('deep', $decoded['a']['b']['c']);
    }

    #[Test]
    public function typesPreserved(): void
    {
        $data = [
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'string' => 'hello',
        ];

        $decoded = $this->serializer->decode($this->serializer->encode($data));

        self::assertSame(42, $decoded['int']);
        self::assertSame(3.14, $decoded['float']);
        self::assertTrue($decoded['bool']);
        self::assertNull($decoded['null']);
        self::assertSame('hello', $decoded['string']);
    }

    #[Test]
    public function encodeEmptyArray(): void
    {
        $encoded = $this->serializer->encode([]);

        self::assertSame('a:0:{}', $encoded);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(PhpSerializer::class);

        self::assertTrue($reflection->isFinal());
    }
}
