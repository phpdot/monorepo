<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Collection;

use PHPdot\MongoDB\Collection\FindQuery;
use PHPdot\MongoDB\Filter\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FindQueryTest extends TestCase
{
    private FindQuery $query;

    protected function setUp(): void
    {
        // FindQuery only needs Collection for execute() — we only test state here
        $reflection = new \ReflectionClass(FindQuery::class);
        $this->query = $reflection->newInstanceWithoutConstructor();

        // Set the collection property to avoid errors if accidentally called
        // collection property left unset — tests only verify getFilter()/getOptions()
    }

    #[Test]
    public function it_starts_with_empty_filter_and_options(): void
    {
        self::assertSame([], $this->query->getFilter());
        self::assertSame([], $this->query->getOptions());
    }

    #[Test]
    public function it_sets_filter_from_array(): void
    {
        $this->query->filter(['status' => 'active']);

        self::assertSame(['status' => 'active'], $this->query->getFilter());
    }

    #[Test]
    public function it_builds_filter_from_callback(): void
    {
        $this->query->where(fn (Filter $f) => $f->eq('status', 'active')->gte('age', 18));

        $filter = $this->query->getFilter();
        self::assertSame(['$eq' => 'active'], $filter['status']);
        self::assertSame(['$gte' => 18], $filter['age']);
    }

    #[Test]
    public function it_sets_projection(): void
    {
        $this->query->projection(['name' => 1, 'email' => 1, '_id' => 0]);

        self::assertSame(['name' => 1, 'email' => 1, '_id' => 0], $this->query->getOptions()['projection']);
    }

    #[Test]
    public function it_sets_sort(): void
    {
        $this->query->sort(['created_at' => -1, 'name' => 1]);

        self::assertSame(['created_at' => -1, 'name' => 1], $this->query->getOptions()['sort']);
    }

    #[Test]
    public function it_sets_limit(): void
    {
        $this->query->limit(10);

        self::assertSame(10, $this->query->getOptions()['limit']);
    }

    #[Test]
    public function it_sets_skip(): void
    {
        $this->query->skip(20);

        self::assertSame(20, $this->query->getOptions()['skip']);
    }

    #[Test]
    public function it_sets_hint(): void
    {
        $this->query->hint('status_1_score_-1');

        self::assertSame('status_1_score_-1', $this->query->getOptions()['hint']);
    }

    #[Test]
    public function it_sets_hint_as_array(): void
    {
        $this->query->hint(['status' => 1, 'score' => -1]);

        self::assertSame(['status' => 1, 'score' => -1], $this->query->getOptions()['hint']);
    }

    #[Test]
    public function it_sets_collation(): void
    {
        $collation = ['locale' => 'en', 'strength' => 2];
        $this->query->collation($collation);

        self::assertSame($collation, $this->query->getOptions()['collation']);
    }

    #[Test]
    public function it_sets_max_time_ms(): void
    {
        $this->query->maxTimeMS(5000);

        self::assertSame(5000, $this->query->getOptions()['maxTimeMS']);
    }

    #[Test]
    public function it_sets_batch_size(): void
    {
        $this->query->batchSize(100);

        self::assertSame(100, $this->query->getOptions()['batchSize']);
    }

    #[Test]
    public function it_sets_allow_disk_use(): void
    {
        $this->query->allowDiskUse();

        self::assertTrue($this->query->getOptions()['allowDiskUse']);
    }

    #[Test]
    public function it_sets_comment(): void
    {
        $this->query->comment('admin dashboard query');

        self::assertSame('admin dashboard query', $this->query->getOptions()['comment']);
    }

    #[Test]
    public function it_sets_arbitrary_option(): void
    {
        $this->query->option('noCursorTimeout', true);

        self::assertTrue($this->query->getOptions()['noCursorTimeout']);
    }

    #[Test]
    public function it_chains_fluently(): void
    {
        $result = $this->query
            ->filter(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->projection(['name' => 1])
            ->limit(10)
            ->skip(20);

        self::assertSame($this->query, $result);
        self::assertSame(['status' => 'active'], $this->query->getFilter());
        self::assertSame(10, $this->query->getOptions()['limit']);
        self::assertSame(20, $this->query->getOptions()['skip']);
    }
}
