<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit;

use PHPdot\Env\Env;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvHelperTest extends TestCase
{
    protected function setUp(): void
    {
        Env::resetInstance();
    }

    protected function tearDown(): void
    {
        Env::resetInstance();
    }

    #[Test]
    public function env_returns_default_when_not_initialized(): void
    {
        self::assertNull(env('APP_NAME'));
        self::assertSame('fallback', env('APP_NAME', 'fallback'));
    }

    #[Test]
    public function init_loads_and_types_values(): void
    {
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        self::assertSame('TestApp', env('APP_NAME'));
        self::assertSame(8080, env('APP_PORT'));
        self::assertTrue(env('APP_DEBUG'));
    }

    #[Test]
    public function env_returns_typed_values(): void
    {
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        // INT
        $port = env('APP_PORT');
        self::assertIsInt($port);
        self::assertSame(8080, $port);

        // BOOL
        $debug = env('APP_DEBUG');
        self::assertIsBool($debug);
        self::assertTrue($debug);

        // FLOAT
        $rate = env('RATE_LIMIT');
        self::assertIsFloat($rate);
        self::assertEqualsWithDelta(1.5, $rate, 0.01);
    }

    #[Test]
    public function env_returns_schema_defaults_for_missing_keys(): void
    {
        // .env.minimal only has DB_HOST (the required key)
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.minimal',
        );

        self::assertSame('DefaultApp', env('APP_NAME'));
        self::assertSame(3000, env('APP_PORT'));
        self::assertFalse(env('APP_DEBUG'));
    }

    #[Test]
    public function env_returns_default_for_unknown_key(): void
    {
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        self::assertNull(env('NONEXISTENT'));
        self::assertSame('custom', env('NONEXISTENT', 'custom'));
    }

    #[Test]
    public function env_static_method_works_directly(): void
    {
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        self::assertSame('TestApp', Env::env('APP_NAME'));
        self::assertNull(Env::env('MISSING'));
        self::assertSame('x', Env::env('MISSING', 'x'));
    }

    #[Test]
    public function get_instance_returns_env(): void
    {
        self::assertNull(Env::getInstance());

        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        self::assertInstanceOf(Env::class, Env::getInstance());
    }

    #[Test]
    public function reset_instance_clears_state(): void
    {
        Env::init(
            __DIR__ . '/../Fixtures/schema.basic.php',
            __DIR__ . '/../Fixtures/.env.basic',
        );

        self::assertNotNull(Env::getInstance());

        Env::resetInstance();

        self::assertNull(Env::getInstance());
        self::assertNull(env('APP_NAME'));
    }

    #[Test]
    public function init_with_missing_env_file_uses_defaults(): void
    {
        // safeCreate skips missing files — only schema defaults are available
        Env::init(
            [
                'MY_KEY' => ['default' => 'hello'],
            ],
            '/nonexistent/.env',
        );

        self::assertSame('hello', env('MY_KEY'));
    }

    #[Test]
    public function init_with_inline_schema(): void
    {
        Env::init(
            [
                'NAME' => ['default' => 'World'],
                'COUNT' => ['type' => 'int', 'default' => 42],
            ],
            [],
        );

        self::assertSame('World', env('NAME'));
        self::assertSame(42, env('COUNT'));
    }
}
