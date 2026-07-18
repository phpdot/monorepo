<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use MongoDB\BSON\Decimal128;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DocumentIntegrationTest extends TestCase
{
    use RequiresMongo;

    private Collection $collection;
    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = self::mongoTestConfig();
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $database = new Database($this->connection);

        try {
            $database->dropCollection('doc_test');
        } catch (\Throwable) {
        }
        $database->createCollection('doc_test');
        $this->collection = $database->collection('doc_test');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_reads_object_id(): void
    {
        $id = new ObjectId();
        $this->collection->insertOne(['_id' => $id, 'name' => 'Test']);

        $doc = $this->collection->findOne(['_id' => $id]);
        self::assertNotNull($doc);
        self::assertInstanceOf(ObjectId::class, $doc->id());
        self::assertSame((string) $id, (string) $doc->id());
    }

    #[Test]
    public function it_reads_utc_date_time_as_datetime_immutable(): void
    {
        $now = new UTCDateTime();
        $this->collection->insertOne(['created_at' => $now]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertInstanceOf(\DateTimeImmutable::class, $doc->created_at);
    }

    #[Test]
    public function it_reads_nested_documents(): void
    {
        $this->collection->insertOne([
            'name' => 'Omar',
            'address' => [
                'street' => '123 Main St',
                'city' => 'Amman',
                'geo' => ['lat' => 31.95, 'lng' => 35.93],
            ],
        ]);

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertInstanceOf(Document::class, $doc->address);
        self::assertSame('Amman', $doc->address->city);

        // Deep nesting
        self::assertInstanceOf(Document::class, $doc->address->geo);
        self::assertEqualsWithDelta(31.95, $doc->address->geo->lat, 0.001);
    }

    #[Test]
    public function it_reads_arrays(): void
    {
        $this->collection->insertOne([
            'name' => 'Omar',
            'tags' => ['php', 'mongodb', 'swoole'],
            'scores' => [95, 87, 92],
        ]);

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertSame(['php', 'mongodb', 'swoole'], $doc->tags);
        self::assertSame([95, 87, 92], $doc->scores);
    }

    #[Test]
    public function it_reads_decimal128(): void
    {
        $this->collection->insertOne([
            'price' => new Decimal128('99.99'),
        ]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);

        // Via __get, Decimal128 passes through (not in convertValue)
        $raw = $doc->getRaw();
        self::assertInstanceOf(Decimal128::class, $raw['price']);

        // Via toArray, it becomes a string
        $array = $doc->toArray();
        self::assertSame('99.99', $array['price']);
    }

    #[Test]
    public function it_reads_boolean_values(): void
    {
        $this->collection->insertOne(['active' => true, 'deleted' => false]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertTrue($doc->active);
        self::assertFalse($doc->deleted);
    }

    #[Test]
    public function it_reads_null_values(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'middle_name' => null]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertNull($doc->middle_name);
        self::assertTrue($doc->has('middle_name'));
    }

    #[Test]
    public function it_reads_integer_values(): void
    {
        $this->collection->insertOne(['count' => 42, 'big' => 9999999999]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertSame(42, $doc->count);
        self::assertIsInt($doc->big);
    }

    #[Test]
    public function it_reads_float_values(): void
    {
        $this->collection->insertOne(['pi' => 3.14159, 'zero' => 0.0]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertEqualsWithDelta(3.14159, $doc->pi, 0.00001);
        self::assertEqualsWithDelta(0.0, $doc->zero, 0.00001);
    }

    #[Test]
    public function it_converts_full_document_to_array(): void
    {
        $id = new ObjectId();
        $this->collection->insertOne([
            '_id' => $id,
            'name' => 'Omar',
            'address' => ['city' => 'Amman'],
            'tags' => ['a', 'b'],
            'created_at' => new UTCDateTime(),
        ]);

        $doc = $this->collection->findOne(['_id' => $id]);
        self::assertNotNull($doc);

        $array = $doc->toArray();
        self::assertIsArray($array);
        self::assertSame('Omar', $array['name']);
        self::assertIsArray($array['address']);
        self::assertSame('Amman', $array['address']['city']);
        self::assertSame(['a', 'b'], $array['tags']);
        self::assertInstanceOf(\DateTimeImmutable::class, $array['created_at']);
        self::assertSame((string) $id, $array['_id']);
    }

    #[Test]
    public function it_converts_document_to_json(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);

        $json = $doc->toJson();
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Omar', $decoded['name']);
        self::assertSame(30, $decoded['age']);
    }

    #[Test]
    public function it_reads_empty_nested_document(): void
    {
        $this->collection->insertOne(['meta' => []]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        // Empty array stays as array
        self::assertSame([], $doc->meta);
    }

    #[Test]
    public function it_reads_array_of_documents(): void
    {
        $this->collection->insertOne([
            'items' => [
                ['name' => 'A', 'qty' => 1],
                ['name' => 'B', 'qty' => 2],
            ],
        ]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);

        $items = $doc->items;
        self::assertIsArray($items);
        self::assertCount(2, $items);
        // Sub-documents in arrays become Document instances
        self::assertInstanceOf(Document::class, $items[0]);
        self::assertSame('A', $items[0]->name);
    }

    #[Test]
    public function it_supports_get_with_default(): void
    {
        $this->collection->insertOne(['name' => 'Omar']);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertSame('Omar', $doc->get('name', 'default'));
        self::assertSame('default', $doc->get('missing', 'default'));
    }

    #[Test]
    public function it_supports_array_access(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
        self::assertTrue(isset($doc['name']));
        self::assertSame('Omar', $doc['name']);
        self::assertFalse(isset($doc['missing']));
    }

    #[Test]
    public function it_supports_json_serialize(): void
    {
        $this->collection->insertOne(['name' => 'Omar']);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);

        $encoded = json_encode($doc, JSON_THROW_ON_ERROR);
        self::assertStringContainsString('Omar', $encoded);
    }

    #[Test]
    public function it_returns_raw_data(): void
    {
        $this->collection->insertOne(['name' => 'Omar']);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);

        $raw = $doc->getRaw();
        self::assertIsArray($raw);
        self::assertArrayHasKey('_id', $raw);
        self::assertArrayHasKey('name', $raw);
    }
}
