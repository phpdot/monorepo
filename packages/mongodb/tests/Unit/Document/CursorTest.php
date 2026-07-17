<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Document;

use PHPdot\MongoDB\Document\Cursor;
use PHPdot\MongoDB\Document\Document;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cursor unit tests that don't require a real MongoDB cursor.
 * Full iteration/lazy/count tests are in Integration/CursorIntegrationTest.
 */
final class CursorTest extends TestCase
{
    #[Test]
    public function it_implements_iterator_aggregate(): void
    {
        $reflection = new \ReflectionClass(Cursor::class);

        self::assertTrue($reflection->implementsInterface(\IteratorAggregate::class));
    }

    #[Test]
    public function it_is_final(): void
    {
        $reflection = new \ReflectionClass(Cursor::class);

        self::assertTrue($reflection->isFinal());
    }

    #[Test]
    public function it_has_expected_public_methods(): void
    {
        $reflection = new \ReflectionClass(Cursor::class);
        $methods = array_map(
            fn (\ReflectionMethod $m) => $m->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        );

        self::assertContains('getIterator', $methods);
        self::assertContains('toArray', $methods);
        self::assertContains('first', $methods);
        self::assertContains('lazy', $methods);
        self::assertContains('count', $methods);
    }
}
