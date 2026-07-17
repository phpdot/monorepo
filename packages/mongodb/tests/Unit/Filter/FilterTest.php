<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Filter;

use PHPdot\MongoDB\Filter\Filter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FilterTest extends TestCase
{
    #[Test]
    public function it_creates_empty_filter(): void
    {
        $filter = Filter::new();

        self::assertSame([], $filter->toArray());
    }

    #[Test]
    public function it_builds_eq_filter(): void
    {
        $filter = Filter::new()->eq('status', 'active');

        self::assertSame(['status' => ['$eq' => 'active']], $filter->toArray());
    }

    #[Test]
    public function it_builds_comparison_operators(): void
    {
        $filter = Filter::new()
            ->gt('age', 18)
            ->gte('score', 90)
            ->lt('attempts', 3)
            ->lte('level', 5)
            ->ne('status', 'deleted');

        $result = $filter->toArray();

        self::assertSame(['$gt' => 18], $result['age']);
        self::assertSame(['$gte' => 90], $result['score']);
        self::assertSame(['$lt' => 3], $result['attempts']);
        self::assertSame(['$lte' => 5], $result['level']);
        self::assertSame(['$ne' => 'deleted'], $result['status']);
    }

    #[Test]
    public function it_combines_operators_on_same_field(): void
    {
        $filter = Filter::new()
            ->gte('age', 18)
            ->lte('age', 65);

        self::assertSame(['age' => ['$gte' => 18, '$lte' => 65]], $filter->toArray());
    }

    #[Test]
    public function it_builds_in_filter(): void
    {
        $filter = Filter::new()->in('status', ['active', 'pending']);

        self::assertSame(['status' => ['$in' => ['active', 'pending']]], $filter->toArray());
    }

    #[Test]
    public function it_builds_nin_filter(): void
    {
        $filter = Filter::new()->nin('role', ['banned', 'suspended']);

        self::assertSame(['role' => ['$nin' => ['banned', 'suspended']]], $filter->toArray());
    }

    #[Test]
    public function it_builds_all_filter(): void
    {
        $filter = Filter::new()->all('tags', ['php', 'mongodb']);

        self::assertSame(['tags' => ['$all' => ['php', 'mongodb']]], $filter->toArray());
    }

    #[Test]
    public function it_builds_size_filter(): void
    {
        $filter = Filter::new()->size('tags', 3);

        self::assertSame(['tags' => ['$size' => 3]], $filter->toArray());
    }

    #[Test]
    public function it_builds_elem_match_filter(): void
    {
        $filter = Filter::new()->elemMatch('scores', ['$gte' => 90, '$lt' => 100]);

        self::assertSame(['scores' => ['$elemMatch' => ['$gte' => 90, '$lt' => 100]]], $filter->toArray());
    }

    #[Test]
    public function it_builds_or_filter(): void
    {
        $filter = Filter::new()->or(
            Filter::new()->eq('role', 'admin'),
            Filter::new()->gt('score', 90),
        );

        $result = $filter->toArray();
        self::assertCount(2, $result['$or']);
        self::assertSame(['role' => ['$eq' => 'admin']], $result['$or'][0]);
        self::assertSame(['score' => ['$gt' => 90]], $result['$or'][1]);
    }

    #[Test]
    public function it_builds_and_filter(): void
    {
        $filter = Filter::new()->and(
            Filter::new()->eq('status', 'active'),
            Filter::new()->gte('age', 18),
        );

        $result = $filter->toArray();
        self::assertCount(2, $result['$and']);
    }

    #[Test]
    public function it_builds_nor_filter(): void
    {
        $filter = Filter::new()->nor(
            Filter::new()->eq('status', 'banned'),
            Filter::new()->eq('role', 'bot'),
        );

        $result = $filter->toArray();
        self::assertCount(2, $result['$nor']);
    }

    #[Test]
    public function it_builds_not_filter(): void
    {
        $filter = Filter::new()->not('age', ['$gt' => 100]);

        self::assertSame(['age' => ['$not' => ['$gt' => 100]]], $filter->toArray());
    }

    #[Test]
    public function it_builds_exists_filter(): void
    {
        $filter = Filter::new()->exists('email');

        self::assertSame(['email' => ['$exists' => true]], $filter->toArray());
    }

    #[Test]
    public function it_builds_not_exists_filter(): void
    {
        $filter = Filter::new()->exists('deletedAt', false);

        self::assertSame(['deletedAt' => ['$exists' => false]], $filter->toArray());
    }

    #[Test]
    public function it_builds_type_filter(): void
    {
        $filter = Filter::new()->type('age', 'int');

        self::assertSame(['age' => ['$type' => 'int']], $filter->toArray());
    }

    #[Test]
    public function it_builds_regex_filter(): void
    {
        $filter = Filter::new()->regex('name', '^Omar', 'i');

        self::assertSame([
            'name' => ['$regex' => '^Omar', '$options' => 'i'],
        ], $filter->toArray());
    }

    #[Test]
    public function it_builds_regex_without_flags(): void
    {
        $filter = Filter::new()->regex('email', '@example\.com$');

        self::assertSame([
            'email' => ['$regex' => '@example\.com$'],
        ], $filter->toArray());
    }

    #[Test]
    public function it_builds_text_search(): void
    {
        $filter = Filter::new()->text('mongodb php');

        self::assertSame([
            '$text' => ['$search' => 'mongodb php'],
        ], $filter->toArray());
    }

    #[Test]
    public function it_builds_text_search_with_options(): void
    {
        $filter = Filter::new()->text('mongodb', ['$language' => 'en', '$caseSensitive' => true]);

        self::assertSame([
            '$text' => ['$search' => 'mongodb', '$language' => 'en', '$caseSensitive' => true],
        ], $filter->toArray());
    }

    #[Test]
    public function it_builds_near_filter(): void
    {
        $filter = Filter::new()->near('location', [35.9, 31.9], maxDistance: 1000.0);

        $result = $filter->toArray();
        self::assertSame([
            '$near' => [
                '$geometry' => ['type' => 'Point', 'coordinates' => [35.9, 31.9]],
                '$maxDistance' => 1000.0,
            ],
        ], $result['location']);
    }

    #[Test]
    public function it_builds_near_with_min_distance(): void
    {
        $filter = Filter::new()->near('location', [35.9, 31.9], maxDistance: 5000.0, minDistance: 100.0);

        $result = $filter->toArray();
        self::assertSame(100.0, $result['location']['$near']['$minDistance']);
        self::assertSame(5000.0, $result['location']['$near']['$maxDistance']);
    }

    #[Test]
    public function it_accepts_raw_filter(): void
    {
        $filter = Filter::new()->raw(['$where' => 'this.age > 18']);

        self::assertSame(['$where' => 'this.age > 18'], $filter->toArray());
    }

    #[Test]
    public function it_chains_multiple_conditions(): void
    {
        $filter = Filter::new()
            ->eq('status', 'active')
            ->gte('age', 18)
            ->in('tags', ['vip', 'premium'])
            ->regex('name', '^Omar', 'i')
            ->or(
                Filter::new()->eq('role', 'admin'),
                Filter::new()->gt('score', 90),
            );

        $result = $filter->toArray();

        self::assertSame(['$eq' => 'active'], $result['status']);
        self::assertSame(['$gte' => 18], $result['age']);
        self::assertSame(['$in' => ['vip', 'premium']], $result['tags']);
        self::assertSame('^Omar', $result['name']['$regex']);
        self::assertCount(2, $result['$or']);
    }
}
