<?php

declare(strict_types=1);

namespace PHPdot\Contracts\Tests\Unit\Pagination;

use PHPdot\Contracts\Pagination\CursorPaginator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CursorPaginatorTest extends TestCase
{
    #[Test]
    public function it_exposes_items_and_metadata(): void
    {
        $paginator = new CursorPaginator([['id' => 1], ['id' => 2]], 2, 'prev', true, 'next');

        self::assertSame([['id' => 1], ['id' => 2]], $paginator->items);
        self::assertSame(2, $paginator->per_page);
        self::assertTrue($paginator->has_more);
    }

    #[Test]
    public function it_carries_the_builder_supplied_cursors(): void
    {
        $paginator = new CursorPaginator([['id' => 1]], 1, 'prev', true, 'next');

        self::assertSame('next', $paginator->next_cursor);
        self::assertSame('prev', $paginator->previous_cursor);
    }

    #[Test]
    public function it_has_no_cursors_on_a_single_page(): void
    {
        $paginator = new CursorPaginator([['id' => 1]], 5, null, false);

        self::assertNull($paginator->next_cursor);
        self::assertNull($paginator->previous_cursor);
        self::assertFalse($paginator->has_more);
    }

    #[Test]
    public function it_encodes_as_the_wire_shape(): void
    {
        $json = json_encode(new CursorPaginator([['id' => 1]], 5, 'prev', true, 'next'), JSON_THROW_ON_ERROR);

        self::assertSame(
            '{"items":[{"id":1}],"per_page":5,"previous_cursor":"prev","has_more":true,"next_cursor":"next"}',
            $json,
        );
    }
}
