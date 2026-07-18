<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Filter\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterIntegrationTest extends TestCase
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
            $database->dropCollection('filter_test');
        } catch (\Throwable) {
        }
        $database->createCollection('filter_test');
        $this->collection = $database->collection('filter_test');

        $this->collection->insertMany([
            ['name' => 'Omar', 'age' => 30, 'status' => 'active', 'tags' => ['php', 'mongodb'], 'score' => 95],
            ['name' => 'Alice', 'age' => 25, 'status' => 'active', 'tags' => ['python'], 'score' => 88],
            ['name' => 'Bob', 'age' => 35, 'status' => 'inactive', 'tags' => ['php', 'go'], 'score' => 72],
            ['name' => 'Charlie', 'age' => 28, 'status' => 'active', 'tags' => ['rust', 'mongodb'], 'score' => 91],
            ['name' => 'Diana', 'age' => 40, 'status' => 'suspended', 'tags' => [], 'score' => 60],
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_filters_with_eq(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->eq('name', 'Omar'))
            ->execute()
            ->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Omar', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_ne(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->ne('status', 'active'))
            ->execute()
            ->toArray();

        self::assertCount(2, $docs);
    }

    #[Test]
    public function it_filters_with_gt_gte_lt_lte(): void
    {
        // gt
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->gt('age', 30))
            ->execute()->toArray();
        self::assertCount(2, $docs);

        // gte
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->gte('age', 30))
            ->execute()->toArray();
        self::assertCount(3, $docs);

        // lt
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->lt('age', 30))
            ->execute()->toArray();
        self::assertCount(2, $docs);

        // lte
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->lte('age', 30))
            ->execute()->toArray();
        self::assertCount(3, $docs);
    }

    #[Test]
    public function it_filters_with_range(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->gte('age', 25)->lte('age', 35))
            ->execute()->toArray();

        self::assertCount(4, $docs);
    }

    #[Test]
    public function it_filters_with_in(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->in('status', ['active', 'suspended']))
            ->execute()->toArray();

        self::assertCount(4, $docs);
    }

    #[Test]
    public function it_filters_with_nin(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->nin('status', ['inactive', 'suspended']))
            ->execute()->toArray();

        self::assertCount(3, $docs);
    }

    #[Test]
    public function it_filters_with_exists(): void
    {
        $this->collection->insertOne(['name' => 'NoScore']);

        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->exists('score'))
            ->execute()->toArray();

        self::assertCount(5, $docs);

        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->exists('score', false))
            ->execute()->toArray();

        self::assertCount(1, $docs);
    }

    #[Test]
    public function it_filters_with_regex(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->regex('name', '^[A-C]', 'i'))
            ->execute()->toArray();

        $names = array_map(fn ($d) => $d->name, $docs);
        sort($names);
        self::assertSame(['Alice', 'Bob', 'Charlie'], $names);
    }

    #[Test]
    public function it_filters_with_or(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->or(
                Filter::new()->eq('name', 'Omar'),
                Filter::new()->eq('name', 'Alice'),
            ))
            ->execute()->toArray();

        self::assertCount(2, $docs);
    }

    #[Test]
    public function it_filters_with_and(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->and(
                Filter::new()->eq('status', 'active'),
                Filter::new()->gte('score', 90),
            ))
            ->execute()->toArray();

        self::assertCount(2, $docs); // Omar (95), Charlie (91)
    }

    #[Test]
    public function it_filters_with_nor(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->nor(
                Filter::new()->eq('status', 'active'),
                Filter::new()->eq('status', 'suspended'),
            ))
            ->execute()->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Bob', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_complex_conditions(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f
                ->eq('status', 'active')
                ->gte('score', 90)
                ->in('tags', ['php']))
            ->execute()->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Omar', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_all_array_operator(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->all('tags', ['php', 'mongodb']))
            ->execute()->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Omar', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_size(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->size('tags', 0))
            ->execute()->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Diana', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_elem_match(): void
    {
        $this->collection->insertOne([
            'name' => 'Nested',
            'results' => [
                ['product' => 'A', 'score' => 90],
                ['product' => 'B', 'score' => 50],
            ],
        ]);

        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->elemMatch('results', ['score' => ['$gte' => 80]]))
            ->execute()->toArray();

        self::assertCount(1, $docs);
        self::assertSame('Nested', $docs[0]->name);
    }

    #[Test]
    public function it_filters_with_type(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->type('age', 'int'))
            ->execute()->toArray();

        self::assertCount(5, $docs);
    }

    #[Test]
    public function it_filters_with_not(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->not('age', ['$gte' => 35]))
            ->execute()->toArray();

        self::assertCount(3, $docs);
    }

    #[Test]
    public function it_filters_with_raw(): void
    {
        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->raw(['age' => ['$mod' => [5, 0]]]))
            ->execute()->toArray();

        // 30, 25, 35, 40 are divisible by 5
        self::assertCount(4, $docs);
    }

    #[Test]
    public function it_filters_with_text_search(): void
    {
        // Create text index first
        $this->collection->createIndex(['name' => 'text']);

        $docs = $this->collection->find()
            ->where(fn (Filter $f) => $f->text('Omar'))
            ->execute()->toArray();

        self::assertCount(1, $docs);
    }
}
