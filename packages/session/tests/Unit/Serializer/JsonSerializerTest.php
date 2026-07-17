<?php

declare(strict_types=1);

namespace PHPdot\Session\Tests\Unit\Serializer;

use PHPdot\Session\Exception\SessionException;
use PHPdot\Session\Serializer\JsonSerializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonSerializerTest extends TestCase
{
    private JsonSerializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new JsonSerializer();
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
    public function encodeEmptyArray(): void
    {
        $encoded = $this->serializer->encode([]);

        self::assertSame('[]', $encoded);
    }

    #[Test]
    public function decodeInvalidJsonThrows(): void
    {
        $this->expectException(SessionException::class);
        $this->expectExceptionMessage('Failed to decode session data');

        $this->serializer->decode('{invalid json');
    }

    #[Test]
    public function decodeNonArrayJsonReturnsEmptyArray(): void
    {
        self::assertSame([], $this->serializer->decode('"just a string"'));
    }

    #[Test]
    public function unicodeSupport(): void
    {
        $data = ['name' => 'Omar'];

        $encoded = $this->serializer->encode($data);
        $decoded = $this->serializer->decode($encoded);

        self::assertSame($data, $decoded);
        self::assertStringContainsString('Omar', $encoded);
    }

    #[Test]
    public function specialCharacters(): void
    {
        $data = ['html' => '<script>alert("xss")</script>', 'path' => '/foo/bar'];

        $encoded = $this->serializer->encode($data);

        self::assertStringContainsString('/foo/bar', $encoded);

        $decoded = $this->serializer->decode($encoded);

        self::assertSame($data, $decoded);
    }

    #[Test]
    public function nullValuesPreserved(): void
    {
        $data = ['nullable' => null];

        $encoded = $this->serializer->encode($data);
        $decoded = $this->serializer->decode($encoded);

        self::assertArrayHasKey('nullable', $decoded);
        self::assertNull($decoded['nullable']);
    }

    #[Test]
    public function booleanValuesPreserved(): void
    {
        $data = ['active' => true, 'deleted' => false];

        $decoded = $this->serializer->decode($this->serializer->encode($data));

        self::assertTrue($decoded['active']);
        self::assertFalse($decoded['deleted']);
    }

    #[Test]
    public function floatValuesPreserved(): void
    {
        $data = ['price' => 19.99];

        $decoded = $this->serializer->decode($this->serializer->encode($data));

        self::assertSame(19.99, $decoded['price']);
    }

    #[Test]
    public function classIsFinal(): void
    {
        $reflection = new \ReflectionClass(JsonSerializer::class);

        self::assertTrue($reflection->isFinal());
    }
}
