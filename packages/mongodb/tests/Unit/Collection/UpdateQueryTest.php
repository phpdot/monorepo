<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Collection;

use PHPdot\MongoDB\Collection\UpdateQuery;
use PHPdot\MongoDB\Filter\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateQueryTest extends TestCase
{
    private function createQuery(bool $many = false): UpdateQuery
    {
        $reflection = new \ReflectionClass(UpdateQuery::class);
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
        self::assertSame([], $query->getUpdate());
        self::assertSame([], $query->getOptions());
    }

    #[Test]
    public function it_sets_filter_from_array(): void
    {
        $query = $this->createQuery();
        $query->filter(['_id' => 'abc']);

        self::assertSame(['_id' => 'abc'], $query->getFilter());
    }

    #[Test]
    public function it_builds_filter_from_callback(): void
    {
        $query = $this->createQuery();
        $query->where(fn (Filter $f) => $f->eq('status', 'inactive')->lt('last_login', 100));

        $filter = $query->getFilter();
        self::assertSame(['$eq' => 'inactive'], $filter['status']);
        self::assertSame(['$lt' => 100], $filter['last_login']);
    }

    #[Test]
    public function it_sets_update_document(): void
    {
        $query = $this->createQuery();
        $update = ['$set' => ['name' => 'Omar'], '$inc' => ['logins' => 1]];
        $query->update($update);

        self::assertSame($update, $query->getUpdate());
    }

    #[Test]
    public function it_sets_upsert(): void
    {
        $query = $this->createQuery();
        $query->upsert();

        self::assertTrue($query->getOptions()['upsert']);
    }

    #[Test]
    public function it_sets_array_filters(): void
    {
        $query = $this->createQuery();
        $filters = [['elem.status' => 'active']];
        $query->arrayFilters($filters);

        self::assertSame($filters, $query->getOptions()['arrayFilters']);
    }

    #[Test]
    public function it_sets_hint(): void
    {
        $query = $this->createQuery();
        $query->hint('status_1');

        self::assertSame('status_1', $query->getOptions()['hint']);
    }

    #[Test]
    public function it_chains_fluently(): void
    {
        $query = $this->createQuery();
        $result = $query
            ->filter(['status' => 'trial'])
            ->update(['$set' => ['status' => 'expired']])
            ->upsert();

        self::assertSame($query, $result);
    }
}
