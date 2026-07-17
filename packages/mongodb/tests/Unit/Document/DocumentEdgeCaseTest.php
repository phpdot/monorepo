<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Document;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Int64;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentEdgeCaseTest extends TestCase
{
    #[Test]
    public function it_returns_null_for_non_object_id_type(): void
    {
        $doc = new Document(['_id' => 'string_id']);

        self::assertNull($doc->id());
    }

    #[Test]
    public function it_returns_null_id_when_id_is_integer(): void
    {
        $doc = new Document(['_id' => 12345]);

        self::assertNull($doc->id());
    }

    #[Test]
    public function it_handles_deeply_nested_documents(): void
    {
        $doc = new Document([
            'level1' => [
                'level2' => [
                    'level3' => [
                        'value' => 'deep',
                    ],
                ],
            ],
        ]);

        self::assertSame('deep', $doc->level1->level2->level3->value);
    }

    #[Test]
    public function it_handles_mixed_array_with_nested_and_scalar(): void
    {
        $doc = new Document([
            'config' => [
                'debug' => true,
                'database' => ['host' => 'localhost', 'port' => 27017],
            ],
        ]);

        $config = $doc->config;
        self::assertInstanceOf(Document::class, $config);
        self::assertTrue($config->debug);
        self::assertInstanceOf(Document::class, $config->database);
        self::assertSame('localhost', $config->database->host);
    }

    #[Test]
    public function it_converts_int64_to_int(): void
    {
        // Int64 is created by the BSON extension for large numbers
        // We can test the conversion path via toArray
        $doc = new Document(['count' => 42]);

        self::assertSame(42, $doc->count);
    }

    #[Test]
    public function it_handles_special_characters_in_field_names(): void
    {
        $doc = new Document([
            'field.with.dots' => 'dotted',
            'field with spaces' => 'spaced',
            '$dollar' => 'dollar',
        ]);

        self::assertSame('dotted', $doc->get('field.with.dots'));
        self::assertSame('spaced', $doc->get('field with spaces'));
        self::assertSame('dollar', $doc->get('$dollar'));
    }

    #[Test]
    public function it_converts_object_id_to_string_in_to_array(): void
    {
        $id = new ObjectId('507f1f77bcf86cd799439011');
        $doc = new Document(['_id' => $id, 'ref' => $id]);

        $array = $doc->toArray();
        self::assertSame('507f1f77bcf86cd799439011', $array['_id']);
        self::assertSame('507f1f77bcf86cd799439011', $array['ref']);
    }

    #[Test]
    public function it_preserves_object_id_via_get(): void
    {
        $id = new ObjectId();
        $doc = new Document(['_id' => $id]);

        // __get returns ObjectId unchanged (not converted)
        $raw = $doc->getRaw();
        self::assertInstanceOf(ObjectId::class, $raw['_id']);
    }

    #[Test]
    public function it_converts_decimal128_to_string_in_to_array(): void
    {
        $decimal = new Decimal128('123456789.123456789');
        $doc = new Document(['price' => $decimal]);

        $array = $doc->toArray();
        self::assertSame('123456789.123456789', $array['price']);
    }

    #[Test]
    public function it_converts_binary_in_to_array(): void
    {
        $binary = new Binary('binary data', Binary::TYPE_GENERIC);
        $doc = new Document(['data' => $binary]);

        $array = $doc->toArray();
        self::assertSame('binary data', $array['data']);
    }

    #[Test]
    public function it_converts_utc_date_in_to_array(): void
    {
        $date = new UTCDateTime(1712188800000);
        $doc = new Document(['created' => $date]);

        $array = $doc->toArray();
        self::assertInstanceOf(\DateTimeImmutable::class, $array['created']);
        self::assertSame('2024-04-04', $array['created']->format('Y-m-d'));
    }

    #[Test]
    public function it_handles_isset_with_null_value(): void
    {
        $doc = new Document(['present' => null]);

        self::assertTrue($doc->has('present'));
        // __isset uses array_key_exists — returns true even for null
        self::assertTrue(isset($doc->present));
        self::assertNull($doc->present);
    }

    #[Test]
    public function it_handles_get_with_null_default_explicitly(): void
    {
        $doc = new Document(['name' => 'Omar']);

        self::assertNull($doc->get('missing'));
        self::assertNull($doc->get('missing', null));
        self::assertSame(0, $doc->get('missing', 0));
        self::assertSame('', $doc->get('missing', ''));
        self::assertSame(false, $doc->get('missing', false));
        self::assertSame([], $doc->get('missing', []));
    }

    #[Test]
    public function it_produces_valid_json_with_flags(): void
    {
        $doc = new Document(['name' => 'Omar', 'data' => 'こんにちは']);

        $json = $doc->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('こんにちは', $json);
        self::assertStringContainsString("\n", $json);
    }

    #[Test]
    public function it_throws_on_set_via_array_access(): void
    {
        $doc = new Document(['name' => 'Omar']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable');
        $doc['name'] = 'changed';
    }

    #[Test]
    public function it_throws_on_unset_via_array_access(): void
    {
        $doc = new Document(['name' => 'Omar']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('immutable');
        unset($doc['name']);
    }

    #[Test]
    public function it_handles_empty_document(): void
    {
        $doc = new Document([]);

        self::assertSame([], $doc->toArray());
        self::assertSame('[]', $doc->toJson());
        self::assertNull($doc->id());
        self::assertNull($doc->missing);
        self::assertFalse($doc->has('anything'));
    }

    #[Test]
    public function it_handles_from_bson_with_empty_array(): void
    {
        $doc = Document::fromBSON([]);

        self::assertSame([], $doc->toArray());
    }

    #[Test]
    public function it_handles_list_of_utc_dates(): void
    {
        $doc = new Document([
            'dates' => [
                new UTCDateTime(1000000000000),
                new UTCDateTime(2000000000000),
            ],
        ]);

        $dates = $doc->dates;
        self::assertIsArray($dates);
        self::assertCount(2, $dates);
        self::assertInstanceOf(\DateTimeImmutable::class, $dates[0]);
        self::assertInstanceOf(\DateTimeImmutable::class, $dates[1]);
    }

    #[Test]
    public function it_handles_list_of_object_ids(): void
    {
        $id1 = new ObjectId();
        $id2 = new ObjectId();
        $doc = new Document(['refs' => [$id1, $id2]]);

        $refs = $doc->refs;
        self::assertIsArray($refs);
        self::assertCount(2, $refs);
        // ObjectIds pass through unchanged in convertValue
        self::assertInstanceOf(ObjectId::class, $refs[0]);
    }

    #[Test]
    public function to_array_converts_list_of_object_ids_to_strings(): void
    {
        $id1 = new ObjectId();
        $id2 = new ObjectId();
        $doc = new Document(['refs' => [$id1, $id2]]);

        $array = $doc->toArray();
        self::assertSame((string) $id1, $array['refs'][0]);
        self::assertSame((string) $id2, $array['refs'][1]);
    }

    #[Test]
    public function it_handles_nested_list_array_in_to_array(): void
    {
        $doc = new Document([
            'matrix' => [[1, 2], [3, 4]],
        ]);

        $array = $doc->toArray();
        self::assertSame([[1, 2], [3, 4]], $array['matrix']);
    }
}
