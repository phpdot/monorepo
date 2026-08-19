<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Collection;

use PHPdot\MongoDB\Collection\FindQuery;
use PHPdot\MongoDB\Exception\InvalidCursorException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guard tests that throw before any query executes — full pagination
 * behavior is covered in Integration/PaginationTest.
 */
final class FindQueryPaginationTest extends TestCase
{
    private FindQuery $query;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(FindQuery::class);
        $this->query = $reflection->newInstanceWithoutConstructor();
    }

    #[Test]
    public function it_rejects_a_page_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->paginate(page: 0);
    }

    #[Test]
    public function it_rejects_a_per_page_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->paginate(page: 1, perPage: 0);
    }

    #[Test]
    public function it_rejects_a_simple_paginate_page_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->simplePaginate(page: -1);
    }

    #[Test]
    public function it_rejects_a_cursor_per_page_below_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->cursorPaginate(perPage: 0);
    }

    #[Test]
    public function it_rejects_an_invalid_direction(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->query->cursorPaginate(direction: 0);
    }

    #[Test]
    public function it_rejects_cursor_pagination_combined_with_skip(): void
    {
        $this->query->skip(10);

        $this->expectException(\LogicException::class);

        $this->query->cursorPaginate();
    }

    #[Test]
    public function it_rejects_a_malformed_cursor_before_querying(): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->query->cursorPaginate(cursor: 'tampered!!cursor');
    }
}
