<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Collection;

use PHPdot\MongoDB\Collection\DeleteQuery;
use PHPdot\MongoDB\Filter\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeleteQueryTest extends TestCase
{
    private function createQuery(bool $many = false): DeleteQuery
    {
        $reflection = new \ReflectionClass(DeleteQuery::class);
        $query = $reflection->newInstanceWithoutConstructor();

        $manyProp = $reflection->getProperty('many');
        $manyProp->setValue($query, $many);

        return $query;
    }

    #[Test]
    public function it_starts_empty(): void
    {
        $query = $this->createQuery();

        self::assertSame([], $query->getFilter());
        self::assertSame([], $query->getOptions());
    }

    #[Test]
    public function it_sets_filter(): void
    {
        $query = $this->createQuery();
        $query->filter(['_id' => 'abc']);

        self::assertSame(['_id' => 'abc'], $query->getFilter());
    }

    #[Test]
    public function it_builds_filter_from_callback(): void
    {
        $query = $this->createQuery();
        $query->where(fn (Filter $f) => $f->lt('expires_at', 1000));

        self::assertSame(['expires_at' => ['$lt' => 1000]], $query->getFilter());
    }

    #[Test]
    public function it_sets_hint(): void
    {
        $query = $this->createQuery();
        $query->hint('status_1');

        self::assertSame('status_1', $query->getOptions()['hint']);
    }

    #[Test]
    public function it_sets_collation(): void
    {
        $query = $this->createQuery();
        $collation = ['locale' => 'en', 'strength' => 2];
        $query->collation($collation);

        self::assertSame($collation, $query->getOptions()['collation']);
    }

    #[Test]
    public function it_sets_arbitrary_option(): void
    {
        $query = $this->createQuery();
        $query->option('comment', 'cleanup job');

        self::assertSame('cleanup job', $query->getOptions()['comment']);
    }

    #[Test]
    public function it_chains_fluently(): void
    {
        $query = $this->createQuery(many: true);
        $result = $query
            ->filter(['status' => 'expired'])
            ->hint('status_1');

        self::assertSame($query, $result);
    }
}
