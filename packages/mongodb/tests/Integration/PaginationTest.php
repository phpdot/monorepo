<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Integration;

use MongoDB\BSON\UTCDateTime;
use PHPdot\MongoDB\Collection\Collection;
use PHPdot\MongoDB\Database\Database;
use PHPdot\MongoDB\Exception\InvalidCursorException;
use PHPdot\MongoDB\Exception\QueryException;
use PHPdot\MongoDB\MongoConnection;
use PHPdot\MongoDB\Pagination\CursorCodec;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
final class PaginationTest extends TestCase
{
    use RequiresMongo;

    private MongoConnection $connection;
    private Collection $collection;

    protected function setUp(): void
    {
        $this->skipUnlessMongoAvailable();

        $this->connection = new MongoConnection(self::mongoTestConfig());
        $this->connection->connect();

        $database = new Database($this->connection);
        try {
            $database->dropCollection('pagination_test');
        } catch (\Throwable) {
        }
        $this->collection = $database->collection('pagination_test');

        $documents = [];
        for ($n = 1; $n <= 25; $n++) {
            $documents[] = [
                'n' => $n,
                'even' => $n % 2 === 0,
                'at' => new UTCDateTime(1723372800000 + $n * 1000),
            ];
        }
        $this->collection->insertMany($documents);
    }

    protected function tearDown(): void
    {
        if (isset($this->connection)) {
            $this->connection->close();
        }
    }

    #[Test]
    public function it_paginates_with_totals(): void
    {
        $page = $this->collection->find()->sort(['n' => 1])->paginate(2, 10);

        self::assertSame(10, count($page->items));
        self::assertSame(25, $page->total);
        self::assertSame(2, $page->current_page);
        self::assertSame(3, $page->last_page);
        self::assertTrue($page->has_more);
        self::assertSame(11, $page->items[0]->n);
    }

    #[Test]
    public function it_paginates_the_last_partial_page(): void
    {
        $page = $this->collection->find()->sort(['n' => 1])->paginate(3, 10);

        self::assertSame(5, count($page->items));
        self::assertFalse($page->has_more);
        self::assertSame(25, $page->items[4]->n);
    }

    #[Test]
    public function it_counts_the_filtered_set_not_the_page(): void
    {
        $page = $this->collection->find()
            ->where(fn($f) => $f->gt('n', 10))
            ->sort(['n' => 1])
            ->paginate(1, 10);

        self::assertSame(15, $page->total);
        self::assertSame(10, count($page->items));
        self::assertSame(11, $page->items[0]->n);
    }

    #[Test]
    public function it_simple_paginates_without_a_total(): void
    {
        $first = $this->collection->find()->sort(['n' => 1])->simplePaginate(1, 10);
        $last = $this->collection->find()->sort(['n' => 1])->simplePaginate(3, 10);

        self::assertSame(-1, $first->total);
        self::assertSame(10, count($first->items));
        self::assertTrue($first->has_more);
        self::assertSame(5, count($last->items));
        self::assertFalse($last->has_more);
    }

    #[Test]
    public function it_cursor_paginates_all_documents_without_gaps(): void
    {
        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $this->collection->find()->cursorPaginate(10, $cursor);
            foreach ($page->items as $document) {
                $seen[] = $document->n;
            }
            $cursor = $page->next_cursor;
            $pages++;
        } while ($cursor !== null);

        self::assertSame(3, $pages);
        self::assertSame(range(1, 25), $seen);
    }

    #[Test]
    public function it_cursor_paginates_descending_on_a_field(): void
    {
        $seen = [];
        $cursor = null;

        do {
            $page = $this->collection->find()->cursorPaginate(10, $cursor, 'n', -1);
            foreach ($page->items as $document) {
                $seen[] = $document->n;
            }
            $cursor = $page->next_cursor;
        } while ($cursor !== null);

        self::assertSame(range(25, 1), $seen);
    }

    #[Test]
    public function it_cursor_paginates_within_a_filter(): void
    {
        $seen = [];
        $cursor = null;

        do {
            $page = $this->collection->find()
                ->filter(['even' => true])
                ->cursorPaginate(5, $cursor, 'n');
            foreach ($page->items as $document) {
                $seen[] = $document->n;
            }
            $cursor = $page->next_cursor;
        } while ($cursor !== null);

        self::assertSame(range(2, 24, 2), $seen);
    }

    #[Test]
    public function it_round_trips_datetime_cursor_fields(): void
    {
        $first = $this->collection->find()->cursorPaginate(10, null, 'at');
        $second = $this->collection->find()->cursorPaginate(10, $first->next_cursor, 'at');

        self::assertSame(range(1, 10), array_map(static fn($d) => $d->n, $first->items));
        self::assertSame(range(11, 20), array_map(static fn($d) => $d->n, $second->items));
    }

    #[Test]
    public function it_rejects_a_tampered_cursor(): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->collection->find()->cursorPaginate(5, 'tampered!!cursor');
    }

    #[Test]
    public function it_throws_when_the_projection_hides_the_cursor_field(): void
    {
        $this->expectException(QueryException::class);

        $this->collection->find()
            ->projection(['_id' => 0, 'n' => 1])
            ->cursorPaginate(10);
    }

    /**
     * The reason cursorPaginateBy() exists: 20 documents sharing 4 timestamps,
     * five each, walked in pages of 4 — so every page edge lands INSIDE a
     * shared group, the exact spot a single-value cursor skips or repeats.
     */
    #[Test]
    public function it_pages_a_non_unique_field_without_skips_or_repeats(): void
    {
        $shared = [];
        for ($n = 100; $n < 120; $n++) {
            $shared[] = [
                'n' => $n,
                'even' => $n % 2 === 0,
                // Four instants, five documents each.
                'at' => new UTCDateTime(1723459200000 + intdiv($n - 100, 5) * 1000),
            ];
        }
        $this->collection->insertMany($shared);

        $seen = [];
        $cursor = null;
        $pages = 0;

        do {
            $page = $this->collection->find()->cursorPaginateBy('at', -1, 4, $cursor);
            foreach ($page->items as $document) {
                $seen[] = $document->n;
            }
            $cursor = $page->next_cursor;
            $pages++;
        } while ($cursor !== null && $pages < 20);

        // Every document exactly once — the 25 seeded plus the 20 shared.
        self::assertCount(45, $seen);
        self::assertSame(45, count(array_unique($seen)));
    }

    #[Test]
    public function it_orders_the_pair_descending_within_a_shared_group(): void
    {
        $shared = [];
        for ($n = 200; $n < 210; $n++) {
            $shared[] = ['n' => $n, 'even' => false, 'at' => new UTCDateTime(1723545600000)];
        }
        $this->collection->insertMany($shared);

        $first = $this->collection->find()
            ->filter(['n' => ['$gte' => 200]])
            ->cursorPaginateBy('at', -1, 4);
        $second = $this->collection->find()
            ->filter(['n' => ['$gte' => 200]])
            ->cursorPaginateBy('at', -1, 4, $first->next_cursor);

        // One shared instant: order falls to _id, descending — insertion order
        // reversed, with the page boundary cutting the group cleanly.
        self::assertSame([209, 208, 207, 206], array_map(static fn($d) => $d->n, $first->items));
        self::assertSame([205, 204, 203, 202], array_map(static fn($d) => $d->n, $second->items));
    }

    #[Test]
    public function it_rejects_a_single_value_cursor_in_pair_mode(): void
    {
        $this->expectException(InvalidCursorException::class);

        $this->collection->find()->cursorPaginateBy('at', -1, 5, CursorCodec::encode(42));
    }

    #[Test]
    public function it_throws_when_the_projection_hides_either_pair_key(): void
    {
        $this->expectException(QueryException::class);

        $this->collection->find()
            ->projection(['_id' => 0, 'at' => 1])
            ->cursorPaginateBy('at', -1, 10);
    }
}
