<?php

declare(strict_types=1);

namespace PHPdot\Contracts\Tests\Unit\Pagination;

use PHPdot\Contracts\Pagination\Paginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    #[Test]
    public function it_exposes_items_and_metadata(): void
    {
        $paginator = new Paginator([['id' => 1], ['id' => 2]], 5, 2, 1);

        self::assertSame([['id' => 1], ['id' => 2]], $paginator->items);
        self::assertSame(5, $paginator->total);
        self::assertSame(2, $paginator->per_page);
        self::assertSame(1, $paginator->current_page);
    }

    #[Test]
    public function it_computes_last_page_from_total(): void
    {
        self::assertSame(3, (new Paginator([], 5, 2, 1))->last_page);
        self::assertSame(1, (new Paginator([], 0, 2, 1))->last_page);
        self::assertSame(1, (new Paginator([], 5, 0, 1))->last_page);
    }

    #[Test]
    public function it_derives_has_more_from_total(): void
    {
        self::assertTrue((new Paginator([['id' => 1]], 5, 2, 1))->has_more);
        self::assertFalse((new Paginator([['id' => 5]], 5, 2, 3))->has_more);
    }

    #[Test]
    public function it_prefers_the_explicit_has_more_flag(): void
    {
        self::assertTrue((new Paginator([['id' => 1]], -1, 2, 1, hasMore: true))->has_more);
        self::assertFalse((new Paginator([], -1, 2, 9, hasMore: false))->has_more);
    }

    #[Test]
    public function it_encodes_as_the_wire_shape(): void
    {
        $json = json_encode(new Paginator([['id' => 1]], 5, 2, 1), JSON_THROW_ON_ERROR);

        self::assertSame(
            '{"items":[{"id":1}],"total":5,"per_page":2,"current_page":1,"last_page":3,"has_more":true}',
            $json,
        );
    }
}
