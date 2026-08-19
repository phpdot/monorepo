<?php

declare(strict_types=1);

namespace PHPdot\MongoDB\Tests\Unit\Pagination;

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use PHPdot\MongoDB\Exception\InvalidCursorException;
use PHPdot\MongoDB\Pagination\CursorCodec;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CursorCodecTest extends TestCase
{
    #[Test]
    public function it_round_trips_an_object_id(): void
    {
        $id = new ObjectId();

        $decoded = CursorCodec::decode(CursorCodec::encode($id));

        self::assertInstanceOf(ObjectId::class, $decoded);
        self::assertSame((string) $id, (string) $decoded);
    }

    #[Test]
    public function it_round_trips_a_utc_datetime(): void
    {
        $date = new UTCDateTime(1723372800000);

        $decoded = CursorCodec::decode(CursorCodec::encode($date));

        self::assertInstanceOf(UTCDateTime::class, $decoded);
        self::assertSame((string) $date, (string) $decoded);
    }

    #[Test]
    public function it_round_trips_scalars(): void
    {
        self::assertSame(42, CursorCodec::decode(CursorCodec::encode(42)));
        self::assertSame(2 ** 40, CursorCodec::decode(CursorCodec::encode(2 ** 40)));
        self::assertSame(1.5, CursorCodec::decode(CursorCodec::encode(1.5)));
        self::assertSame('after-me', CursorCodec::decode(CursorCodec::encode('after-me')));
        self::assertNull(CursorCodec::decode(CursorCodec::encode(null)));
    }

    #[Test]
    public function it_produces_an_opaque_base64_string(): void
    {
        $cursor = CursorCodec::encode(new ObjectId());

        self::assertNotFalse(base64_decode($cursor, true));
        self::assertStringContainsString('$oid', (string) base64_decode($cursor, true));
    }

    #[Test]
    public function it_rejects_a_cursor_that_is_not_base64(): void
    {
        $this->expectException(InvalidCursorException::class);

        CursorCodec::decode('not!!base64');
    }

    #[Test]
    public function it_rejects_a_cursor_that_is_not_json(): void
    {
        $this->expectException(InvalidCursorException::class);

        CursorCodec::decode(base64_encode('{"v"'));
    }

    #[Test]
    public function it_rejects_a_cursor_without_a_value(): void
    {
        $this->expectException(InvalidCursorException::class);

        CursorCodec::decode(base64_encode('{"x": 1}'));
    }
}
