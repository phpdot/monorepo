<?php

declare(strict_types=1);

namespace PHPdot\Cache\Tests\Unit;

use PHPdot\Cache\Serializer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SerializerTest extends TestCase
{
    private Serializer $serializer;

    protected function setUp(): void
    {
        $this->serializer = new Serializer();
    }

    #[Test]
    public function serializesAndUnserializesString(): void
    {
        $data = $this->serializer->serialize('hello');
        $result = $this->serializer->unserialize($data);

        self::assertSame('hello', $result);
    }

    #[Test]
    public function serializesAndUnserializesInteger(): void
    {
        $data = $this->serializer->serialize(42);
        $result = $this->serializer->unserialize($data);

        self::assertSame(42, $result);
    }

    #[Test]
    public function serializesAndUnserializesArray(): void
    {
        $array = ['key' => 'value', 'nested' => [1, 2, 3]];
        $data = $this->serializer->serialize($array);
        $result = $this->serializer->unserialize($data);

        self::assertSame($array, $result);
    }

    #[Test]
    public function serializesAndUnserializesObject(): void
    {
        $obj = new \stdClass();
        $obj->name = 'test';
        $obj->value = 123;

        $data = $this->serializer->serialize($obj);
        $result = $this->serializer->unserialize($data);

        self::assertEquals($obj, $result);
    }

    #[Test]
    public function serializesAndUnserializesNull(): void
    {
        $data = $this->serializer->serialize(null);
        $result = $this->serializer->unserialize($data);

        self::assertNull($result);
    }

    #[Test]
    public function roundTripPreservesTypes(): void
    {
        $values = [
            'string' => 'hello',
            'int' => 42,
            'float' => 3.14,
            'bool' => true,
            'array' => [1, 'two', 3.0],
            'null' => null,
        ];

        foreach ($values as $label => $value) {
            $serialized = $this->serializer->serialize($value);
            $unserialized = $this->serializer->unserialize($serialized);

            self::assertSame($value, $unserialized, "Round-trip failed for type: {$label}");
        }
    }
}
