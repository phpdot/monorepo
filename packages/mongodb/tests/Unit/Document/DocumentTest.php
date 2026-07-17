<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Document;

use DateTimeImmutable;
use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentTest extends TestCase
{
    #[Test]
    public function it_accesses_scalar_fields(): void
    {
        $doc = new Document(['name' => 'Omar', 'age' => 30, 'active' => true]);

        self::assertSame('Omar', $doc->name);
        self::assertSame(30, $doc->age);
        self::assertTrue($doc->active);
    }

    #[Test]
    public function it_returns_null_for_missing_fields(): void
    {
        $doc = new Document(['name' => 'Omar']);

        self::assertNull($doc->missing);
    }

    #[Test]
    public function it_checks_field_existence(): void
    {
        $doc = new Document(['name' => 'Omar', 'nullField' => null]);

        self::assertTrue($doc->has('name'));
        self::assertTrue($doc->has('nullField'));
        self::assertFalse($doc->has('missing'));
        self::assertTrue(isset($doc->name));
        self::assertFalse(isset($doc->missing));
    }

    #[Test]
    public function it_gets_field_with_default(): void
    {
        $doc = new Document(['name' => 'Omar']);

        self::assertSame('Omar', $doc->get('name', 'default'));
        self::assertSame('default', $doc->get('missing', 'default'));
    }

    #[Test]
    public function it_returns_object_id(): void
    {
        $id = new ObjectId();
        $doc = new Document(['_id' => $id, 'name' => 'Omar']);

        self::assertSame($id, $doc->id());
    }

    #[Test]
    public function it_returns_null_id_when_missing(): void
    {
        $doc = new Document(['name' => 'Omar']);

        self::assertNull($doc->id());
    }

    #[Test]
    public function it_converts_utc_date_time(): void
    {
        $timestamp = new UTCDateTime(1712188800000); // 2024-04-04 00:00:00 UTC
        $doc = new Document(['created_at' => $timestamp]);

        $result = $doc->created_at;
        self::assertInstanceOf(DateTimeImmutable::class, $result);
        self::assertSame('2024-04-04', $result->format('Y-m-d'));
    }

    #[Test]
    public function it_converts_binary_to_string(): void
    {
        $binary = new Binary('hello world', Binary::TYPE_GENERIC);
        $doc = new Document(['data' => $binary]);

        self::assertSame('hello world', $doc->data);
    }

    #[Test]
    public function it_converts_nested_associative_arrays_to_documents(): void
    {
        $doc = new Document([
            'address' => ['city' => 'Amman', 'country' => 'Jordan'],
        ]);

        $address = $doc->address;
        self::assertInstanceOf(Document::class, $address);
        self::assertSame('Amman', $address->city);
        self::assertSame('Jordan', $address->country);
    }

    #[Test]
    public function it_preserves_list_arrays(): void
    {
        $doc = new Document(['tags' => ['php', 'mongodb', 'swoole']]);

        self::assertSame(['php', 'mongodb', 'swoole'], $doc->tags);
    }

    #[Test]
    public function it_converts_to_plain_array(): void
    {
        $id = new ObjectId('507f1f77bcf86cd799439011');
        $doc = new Document([
            '_id' => $id,
            'name' => 'Omar',
            'address' => ['city' => 'Amman'],
            'tags' => ['vip', 'premium'],
        ]);

        $array = $doc->toArray();

        self::assertSame('507f1f77bcf86cd799439011', $array['_id']);
        self::assertSame('Omar', $array['name']);
        self::assertIsArray($array['address']);
        self::assertSame('Amman', $array['address']['city']);
        self::assertSame(['vip', 'premium'], $array['tags']);
    }

    #[Test]
    public function it_converts_to_json(): void
    {
        $doc = new Document(['name' => 'Omar', 'age' => 30]);

        $json = $doc->toJson();

        self::assertSame('{"name":"Omar","age":30}', $json);
    }

    #[Test]
    public function it_implements_array_access(): void
    {
        $doc = new Document(['name' => 'Omar', 'age' => 30]);

        self::assertTrue(isset($doc['name']));
        self::assertSame('Omar', $doc['name']);
        self::assertFalse(isset($doc['missing']));
    }

    #[Test]
    public function it_is_immutable_via_array_access(): void
    {
        $doc = new Document(['name' => 'Omar']);

        $this->expectException(\LogicException::class);
        $doc['name'] = 'changed';
    }

    #[Test]
    public function it_cannot_unset_via_array_access(): void
    {
        $doc = new Document(['name' => 'Omar']);

        $this->expectException(\LogicException::class);
        unset($doc['name']);
    }

    #[Test]
    public function it_implements_json_serializable(): void
    {
        $doc = new Document(['name' => 'Omar']);

        self::assertSame('{"name":"Omar"}', json_encode($doc, JSON_THROW_ON_ERROR));
    }

    #[Test]
    public function it_returns_raw_data(): void
    {
        $data = ['name' => 'Omar', 'age' => 30];
        $doc = new Document($data);

        self::assertSame($data, $doc->getRaw());
    }

    #[Test]
    public function it_converts_decimal128_to_string_in_to_array(): void
    {
        $decimal = new Decimal128('1500.75');
        $doc = new Document(['balance' => $decimal]);

        $array = $doc->toArray();
        self::assertSame('1500.75', $array['balance']);
    }

    #[Test]
    public function it_creates_from_bson_array(): void
    {
        $doc = Document::fromBSON(['name' => 'Omar', 'age' => 30]);

        self::assertSame('Omar', $doc->name);
        self::assertSame(30, $doc->age);
    }

    #[Test]
    public function it_handles_empty_arrays(): void
    {
        $doc = new Document(['items' => []]);

        self::assertSame([], $doc->items);
    }

    #[Test]
    public function it_converts_utc_date_time_in_to_array(): void
    {
        $timestamp = new UTCDateTime(1712188800000);
        $doc = new Document(['created_at' => $timestamp]);

        $array = $doc->toArray();
        self::assertInstanceOf(DateTimeImmutable::class, $array['created_at']);
    }

    #[Test]
    public function it_converts_nested_utc_date_time_in_nested_documents(): void
    {
        $doc = new Document([
            'meta' => [
                'created_at' => new UTCDateTime(1712188800000),
                'name' => 'test',
            ],
        ]);

        $meta = $doc->meta;
        self::assertInstanceOf(Document::class, $meta);
        self::assertInstanceOf(DateTimeImmutable::class, $meta->created_at);
    }
}
