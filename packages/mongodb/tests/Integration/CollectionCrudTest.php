<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use MongoDB\BSON\ObjectId;
use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CollectionCrudTest extends TestCase
{
    use RequiresMongo;

    private Database $database;
    private Collection $collection;
    private MongoConnection $connection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $config = self::mongoTestConfig();
        $this->connection = new MongoConnection($config);
        $this->connection->connect();
        $this->database = new Database($this->connection);

        // Clean collection before each test
        try {
            $this->database->dropCollection('crud_test');
        } catch (\Throwable) {
        }
        $this->database->createCollection('crud_test');
        $this->collection = $this->database->collection('crud_test');
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    // ─── insertOne ───

    #[Test]
    public function it_inserts_one_document(): void
    {
        $result = $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        self::assertNotNull($result->getInsertedId());
        self::assertSame(1, $this->collection->countDocuments());
    }

    #[Test]
    public function it_inserts_with_custom_id(): void
    {
        $id = new ObjectId();
        $this->collection->insertOne(['_id' => $id, 'name' => 'Custom']);

        $doc = $this->collection->findOne(['_id' => $id]);
        self::assertNotNull($doc);
        self::assertSame('Custom', $doc->name);
    }

    #[Test]
    public function it_inserts_empty_document(): void
    {
        $result = $this->collection->insertOne([]);
        self::assertNotNull($result->getInsertedId());
    }

    #[Test]
    public function it_inserts_document_with_nested_data(): void
    {
        $this->collection->insertOne([
            'name' => 'Omar',
            'address' => ['city' => 'Amman', 'country' => 'Jordan'],
            'tags' => ['php', 'mongodb'],
            'meta' => ['created' => true, 'count' => 42],
        ]);

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertInstanceOf(Document::class, $doc->address);
        self::assertSame('Amman', $doc->address->city);
        self::assertSame(['php', 'mongodb'], $doc->tags);
    }

    // ─── insertMany ───

    #[Test]
    public function it_inserts_many_documents(): void
    {
        $result = $this->collection->insertMany([
            ['name' => 'Alice', 'age' => 25],
            ['name' => 'Bob', 'age' => 30],
            ['name' => 'Charlie', 'age' => 35],
        ]);

        self::assertSame(3, $result->getInsertedCount());
        self::assertCount(3, $result->getInsertedIds());
        self::assertSame(3, $this->collection->countDocuments());
    }

    #[Test]
    public function it_inserts_many_with_single_document(): void
    {
        $result = $this->collection->insertMany([['name' => 'Solo']]);
        self::assertSame(1, $result->getInsertedCount());
    }

    // ─── findOne ───

    #[Test]
    public function it_finds_one_document(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'role' => 'admin']);

        $doc = $this->collection->findOne(['name' => 'Omar']);

        self::assertNotNull($doc);
        self::assertInstanceOf(Document::class, $doc);
        self::assertSame('Omar', $doc->name);
        self::assertSame('admin', $doc->role);
    }

    #[Test]
    public function it_returns_null_when_not_found(): void
    {
        $doc = $this->collection->findOne(['name' => 'nonexistent']);

        self::assertNull($doc);
    }

    #[Test]
    public function it_finds_one_with_empty_filter(): void
    {
        $this->collection->insertOne(['name' => 'First']);

        $doc = $this->collection->findOne();
        self::assertNotNull($doc);
    }

    #[Test]
    public function it_finds_one_with_options(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'secret' => 'hidden']);

        $doc = $this->collection->findOne(
            ['name' => 'Omar'],
            ['projection' => ['name' => 1, '_id' => 0]],
        );

        self::assertNotNull($doc);
        self::assertSame('Omar', $doc->name);
        self::assertNull($doc->secret);
        self::assertNull($doc->id());
    }

    // ─── find (fluent) ───

    #[Test]
    public function it_finds_documents_with_fluent_builder(): void
    {
        $this->seedUsers();

        $cursor = $this->collection->find()
            ->filter(['status' => 'active'])
            ->sort(['age' => 1])
            ->execute();

        $docs = $cursor->toArray();
        self::assertCount(3, $docs);
        self::assertSame('Alice', $docs[0]->name);
    }

    #[Test]
    public function it_finds_with_projection(): void
    {
        $this->seedUsers();

        $cursor = $this->collection->find()
            ->filter(['name' => 'Omar'])
            ->projection(['name' => 1, '_id' => 0])
            ->execute();

        $docs = $cursor->toArray();
        self::assertCount(1, $docs);
        self::assertSame('Omar', $docs[0]->name);
        self::assertNull($docs[0]->id());
    }

    #[Test]
    public function it_finds_with_limit_and_skip(): void
    {
        $this->seedUsers();

        $docs = $this->collection->find()
            ->filter(['status' => 'active'])
            ->sort(['name' => 1])
            ->limit(2)
            ->skip(1)
            ->execute()
            ->toArray();

        self::assertCount(2, $docs);
    }

    #[Test]
    public function it_finds_first(): void
    {
        $this->seedUsers();

        $doc = $this->collection->find()
            ->filter(['status' => 'active'])
            ->sort(['age' => 1])
            ->first();

        self::assertNotNull($doc);
        self::assertSame('Alice', $doc->name);
    }

    #[Test]
    public function it_finds_first_returns_null_when_empty(): void
    {
        $doc = $this->collection->find()
            ->filter(['status' => 'nonexistent'])
            ->first();

        self::assertNull($doc);
    }

    #[Test]
    public function it_counts_via_find_builder(): void
    {
        $this->seedUsers();

        $count = $this->collection->find()
            ->filter(['status' => 'active'])
            ->count();

        self::assertSame(3, $count);
    }

    #[Test]
    public function it_finds_with_where_callback(): void
    {
        $this->seedUsers();

        $docs = $this->collection->find()
            ->where(fn (\PHPdot\MongoDB\Filter\Filter $f) => $f
                ->eq('status', 'active')
                ->gte('age', 30))
            ->sort(['age' => 1])
            ->execute()
            ->toArray();

        self::assertCount(2, $docs);
        self::assertSame('Omar', $docs[0]->name);
    }

    #[Test]
    public function it_finds_with_comment(): void
    {
        $this->seedUsers();

        $docs = $this->collection->find()
            ->filter(['status' => 'active'])
            ->comment('integration test query')
            ->execute()
            ->toArray();

        self::assertCount(3, $docs);
    }

    #[Test]
    public function it_finds_with_batch_size(): void
    {
        $this->seedUsers();

        $docs = $this->collection->find()
            ->filter(['status' => 'active'])
            ->batchSize(1)
            ->execute()
            ->toArray();

        self::assertCount(3, $docs);
    }

    #[Test]
    public function it_finds_with_allow_disk_use(): void
    {
        $this->seedUsers();

        $docs = $this->collection->find()
            ->filter([])
            ->allowDiskUse()
            ->execute()
            ->toArray();

        self::assertGreaterThan(0, count($docs));
    }

    // ─── updateOne (quick access) ───

    #[Test]
    public function it_updates_one_document_quick(): void
    {
        $this->seedUsers();

        $result = $this->collection->executeUpdateQuery(
            ['name' => 'Omar'],
            ['$set' => ['age' => 31]],
            [],
            false,
        );

        self::assertSame(1, $result->getModifiedCount());

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertSame(31, $doc->age);
    }

    // ─── updateOne (fluent) ───

    #[Test]
    public function it_updates_one_with_fluent_builder(): void
    {
        $this->seedUsers();

        $result = $this->collection->updateOne()
            ->filter(['name' => 'Omar'])
            ->update(['$set' => ['age' => 31, 'updated' => true]])
            ->execute();

        self::assertSame(1, $result->getModifiedCount());

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertSame(31, $doc->age);
        self::assertTrue($doc->updated);
    }

    #[Test]
    public function it_updates_one_with_where_callback(): void
    {
        $this->seedUsers();

        $result = $this->collection->updateOne()
            ->where(fn (\PHPdot\MongoDB\Filter\Filter $f) => $f->eq('name', 'Alice'))
            ->update(['$inc' => ['age' => 1]])
            ->execute();

        self::assertSame(1, $result->getModifiedCount());

        $doc = $this->collection->findOne(['name' => 'Alice']);
        self::assertNotNull($doc);
        self::assertSame(26, $doc->age);
    }

    #[Test]
    public function it_updates_one_with_upsert(): void
    {
        $result = $this->collection->updateOne()
            ->filter(['name' => 'NewUser'])
            ->update(['$set' => ['name' => 'NewUser', 'age' => 20]])
            ->upsert()
            ->execute();

        self::assertSame(1, $result->getUpsertedCount());

        $doc = $this->collection->findOne(['name' => 'NewUser']);
        self::assertNotNull($doc);
    }

    #[Test]
    public function it_updates_one_no_match_returns_zero(): void
    {
        $result = $this->collection->updateOne()
            ->filter(['name' => 'Ghost'])
            ->update(['$set' => ['age' => 99]])
            ->execute();

        self::assertSame(0, $result->getModifiedCount());
        self::assertSame(0, $result->getMatchedCount());
    }

    // ─── updateMany (fluent) ───

    #[Test]
    public function it_updates_many_documents(): void
    {
        $this->seedUsers();

        $result = $this->collection->updateMany()
            ->filter(['status' => 'active'])
            ->update(['$set' => ['verified' => true]])
            ->execute();

        self::assertSame(3, $result->getModifiedCount());

        $count = $this->collection->countDocuments(['verified' => true]);
        self::assertSame(3, $count);
    }

    #[Test]
    public function it_updates_many_with_where(): void
    {
        $this->seedUsers();

        $result = $this->collection->updateMany()
            ->where(fn (\PHPdot\MongoDB\Filter\Filter $f) => $f->gte('age', 30))
            ->update(['$set' => ['senior' => true]])
            ->execute();

        self::assertSame(2, $result->getModifiedCount());
    }

    // ─── deleteOne (fluent) ───

    #[Test]
    public function it_deletes_one_document(): void
    {
        $this->seedUsers();

        $result = $this->collection->deleteOne()
            ->filter(['name' => 'Omar'])
            ->execute();

        self::assertSame(1, $result->getDeletedCount());
        self::assertSame(3, $this->collection->countDocuments());
    }

    #[Test]
    public function it_deletes_one_with_where(): void
    {
        $this->seedUsers();

        $result = $this->collection->deleteOne()
            ->where(fn (\PHPdot\MongoDB\Filter\Filter $f) => $f->eq('status', 'inactive'))
            ->execute();

        self::assertSame(1, $result->getDeletedCount());
    }

    #[Test]
    public function it_deletes_one_no_match_returns_zero(): void
    {
        $result = $this->collection->deleteOne()
            ->filter(['name' => 'Ghost'])
            ->execute();

        self::assertSame(0, $result->getDeletedCount());
    }

    // ─── deleteMany (fluent) ───

    #[Test]
    public function it_deletes_many_documents(): void
    {
        $this->seedUsers();

        $result = $this->collection->deleteMany()
            ->filter(['status' => 'active'])
            ->execute();

        self::assertSame(3, $result->getDeletedCount());
        self::assertSame(1, $this->collection->countDocuments());
    }

    #[Test]
    public function it_deletes_many_with_where(): void
    {
        $this->seedUsers();

        $result = $this->collection->deleteMany()
            ->where(fn (\PHPdot\MongoDB\Filter\Filter $f) => $f->gte('age', 30))
            ->execute();

        self::assertSame(2, $result->getDeletedCount());
    }

    // ─── replaceOne ───

    #[Test]
    public function it_replaces_one_document(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $result = $this->collection->replaceOne(
            ['name' => 'Omar'],
            ['name' => 'Omar', 'age' => 31, 'replaced' => true],
        );

        self::assertSame(1, $result->getModifiedCount());

        $doc = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($doc);
        self::assertSame(31, $doc->age);
        self::assertTrue($doc->replaced);
    }

    #[Test]
    public function it_replaces_one_no_match(): void
    {
        $result = $this->collection->replaceOne(
            ['name' => 'Ghost'],
            ['name' => 'Ghost', 'age' => 0],
        );

        self::assertSame(0, $result->getMatchedCount());
    }

    // ─── countDocuments ───

    #[Test]
    public function it_counts_all_documents(): void
    {
        $this->seedUsers();

        self::assertSame(4, $this->collection->countDocuments());
    }

    #[Test]
    public function it_counts_with_filter(): void
    {
        $this->seedUsers();

        self::assertSame(3, $this->collection->countDocuments(['status' => 'active']));
        self::assertSame(1, $this->collection->countDocuments(['status' => 'inactive']));
    }

    #[Test]
    public function it_counts_empty_collection(): void
    {
        self::assertSame(0, $this->collection->countDocuments());
    }

    // ─── estimatedDocumentCount ───

    #[Test]
    public function it_estimates_document_count(): void
    {
        $this->seedUsers();

        $count = $this->collection->estimatedDocumentCount();
        self::assertSame(4, $count);
    }

    // ─── distinct ───

    #[Test]
    public function it_returns_distinct_values(): void
    {
        $this->seedUsers();

        $statuses = $this->collection->distinct('status');
        sort($statuses);

        self::assertSame(['active', 'inactive'], $statuses);
    }

    #[Test]
    public function it_returns_distinct_with_filter(): void
    {
        $this->seedUsers();

        $names = $this->collection->distinct('name', ['status' => 'active']);
        sort($names);

        self::assertSame(['Alice', 'Charlie', 'Omar'], $names);
    }

    #[Test]
    public function it_returns_empty_distinct_for_missing_field(): void
    {
        $this->seedUsers();

        $values = $this->collection->distinct('nonexistent');
        self::assertSame([], $values);
    }

    // ─── findOneAndUpdate ───

    #[Test]
    public function it_finds_one_and_updates(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $doc = $this->collection->findOneAndUpdate(
            ['name' => 'Omar'],
            ['$set' => ['age' => 31]],
        );

        // Returns the document BEFORE update by default
        self::assertNotNull($doc);
        self::assertSame('Omar', $doc->name);
        self::assertSame(30, $doc->age);

        // Verify the update happened
        $updated = $this->collection->findOne(['name' => 'Omar']);
        self::assertNotNull($updated);
        self::assertSame(31, $updated->age);
    }

    #[Test]
    public function it_finds_one_and_updates_returns_null_when_not_found(): void
    {
        $doc = $this->collection->findOneAndUpdate(
            ['name' => 'Ghost'],
            ['$set' => ['age' => 0]],
        );

        self::assertNull($doc);
    }

    // ─── findOneAndReplace ───

    #[Test]
    public function it_finds_one_and_replaces(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $doc = $this->collection->findOneAndReplace(
            ['name' => 'Omar'],
            ['name' => 'Omar', 'age' => 31, 'replaced' => true],
        );

        self::assertNotNull($doc);
        self::assertSame(30, $doc->age);
    }

    #[Test]
    public function it_finds_one_and_replaces_returns_null_when_not_found(): void
    {
        $doc = $this->collection->findOneAndReplace(
            ['name' => 'Ghost'],
            ['name' => 'Ghost'],
        );

        self::assertNull($doc);
    }

    // ─── findOneAndDelete ───

    #[Test]
    public function it_finds_one_and_deletes(): void
    {
        $this->collection->insertOne(['name' => 'Omar', 'age' => 30]);

        $doc = $this->collection->findOneAndDelete(['name' => 'Omar']);

        self::assertNotNull($doc);
        self::assertSame('Omar', $doc->name);
        self::assertSame(0, $this->collection->countDocuments());
    }

    #[Test]
    public function it_finds_one_and_deletes_returns_null_when_not_found(): void
    {
        $doc = $this->collection->findOneAndDelete(['name' => 'Ghost']);

        self::assertNull($doc);
    }

    // ─── bulkWrite ───

    #[Test]
    public function it_executes_bulk_write(): void
    {
        $result = $this->collection->bulkWrite([
            ['insertOne' => [['name' => 'A', 'age' => 1]]],
            ['insertOne' => [['name' => 'B', 'age' => 2]]],
            ['insertOne' => [['name' => 'C', 'age' => 3]]],
        ]);

        self::assertSame(3, $result->getInsertedCount());
        self::assertSame(3, $this->collection->countDocuments());
    }

    #[Test]
    public function it_executes_mixed_bulk_write(): void
    {
        $this->collection->insertOne(['name' => 'Existing', 'age' => 10]);

        $result = $this->collection->bulkWrite([
            ['insertOne' => [['name' => 'New', 'age' => 20]]],
            ['updateOne' => [['name' => 'Existing'], ['$set' => ['age' => 11]]]],
            ['deleteOne' => [['name' => 'New']]],
        ]);

        self::assertSame(1, $result->getInsertedCount());
        self::assertSame(1, $result->getModifiedCount());
        self::assertSame(1, $result->getDeletedCount());
        self::assertSame(1, $this->collection->countDocuments());
    }

    // ─── Utilities ───

    #[Test]
    public function it_returns_collection_name(): void
    {
        self::assertSame('crud_test', $this->collection->getName());
    }

    #[Test]
    public function it_returns_collection_namespace(): void
    {
        self::assertSame('phpdot_test.crud_test', $this->collection->getNamespace());
    }

    #[Test]
    public function it_returns_raw_collection(): void
    {
        self::assertInstanceOf(\MongoDB\Collection::class, $this->collection->raw());
    }

    #[Test]
    public function it_creates_a_filter_builder(): void
    {
        $filter = $this->collection->filter();

        self::assertInstanceOf(\PHPdot\MongoDB\Filter\Filter::class, $filter);
    }

    private function seedUsers(): void
    {
        $this->collection->insertMany([
            ['name' => 'Omar', 'age' => 30, 'status' => 'active'],
            ['name' => 'Alice', 'age' => 25, 'status' => 'active'],
            ['name' => 'Bob', 'age' => 28, 'status' => 'inactive'],
            ['name' => 'Charlie', 'age' => 35, 'status' => 'active'],
        ]);
    }
}
