<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Error\HttpStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpStatusTest extends TestCase
{
    #[Test]
    public function it_has_success_codes(): void
    {
        self::assertSame(200, HttpStatus::OK->value);
        self::assertSame(201, HttpStatus::CREATED->value);
        self::assertSame(202, HttpStatus::ACCEPTED->value);
        self::assertSame(204, HttpStatus::NO_CONTENT->value);
    }

    #[Test]
    public function it_has_redirect_codes(): void
    {
        self::assertSame(301, HttpStatus::MOVED_PERMANENTLY->value);
        self::assertSame(302, HttpStatus::FOUND->value);
        self::assertSame(304, HttpStatus::NOT_MODIFIED->value);
        self::assertSame(307, HttpStatus::TEMPORARY_REDIRECT->value);
        self::assertSame(308, HttpStatus::PERMANENT_REDIRECT->value);
    }

    #[Test]
    public function it_has_client_error_codes(): void
    {
        self::assertSame(400, HttpStatus::BAD_REQUEST->value);
        self::assertSame(401, HttpStatus::UNAUTHORIZED->value);
        self::assertSame(403, HttpStatus::FORBIDDEN->value);
        self::assertSame(404, HttpStatus::NOT_FOUND->value);
        self::assertSame(405, HttpStatus::METHOD_NOT_ALLOWED->value);
        self::assertSame(409, HttpStatus::CONFLICT->value);
        self::assertSame(410, HttpStatus::GONE->value);
        self::assertSame(413, HttpStatus::PAYLOAD_TOO_LARGE->value);
        self::assertSame(415, HttpStatus::UNSUPPORTED_MEDIA->value);
        self::assertSame(422, HttpStatus::UNPROCESSABLE_ENTITY->value);
        self::assertSame(429, HttpStatus::TOO_MANY_REQUESTS->value);
    }

    #[Test]
    public function it_has_server_error_codes(): void
    {
        self::assertSame(500, HttpStatus::INTERNAL_SERVER_ERROR->value);
        self::assertSame(502, HttpStatus::BAD_GATEWAY->value);
        self::assertSame(503, HttpStatus::SERVICE_UNAVAILABLE->value);
        self::assertSame(504, HttpStatus::GATEWAY_TIMEOUT->value);
    }

    #[Test]
    public function it_creates_from_value(): void
    {
        self::assertSame(HttpStatus::NOT_FOUND, HttpStatus::from(404));
        self::assertSame(HttpStatus::OK, HttpStatus::from(200));
    }

    #[Test]
    public function it_returns_null_for_unknown_code(): void
    {
        self::assertNull(HttpStatus::tryFrom(999));
    }
}
