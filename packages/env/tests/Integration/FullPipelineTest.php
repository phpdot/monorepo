<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Integration;

use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Env;
use PHPdot\Env\Exception\ParseException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FullPipelineTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/';

    #[Test]
    public function parseBasicWithSchemaReturnsTypedValues(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame('TestApp', $env->get('APP_NAME'));
        self::assertSame(8080, $env->get('APP_PORT'));
        self::assertTrue($env->get('APP_DEBUG'));
        self::assertSame(1.5, $env->get('RATE_LIMIT'));
        self::assertSame('localhost', $env->get('DB_HOST'));
    }

    #[Test]
    public function parseComplexAllQuoteStylesWork(): void
    {
        $schema = [
            'DB_HOST' => ['default' => ''],
            'DB_PORT' => ['type' => EnvType::INT, 'default' => 0],
            'DB_NAME' => ['default' => ''],
            'DB_PASS' => ['default' => ''],
            'QUOTED_HASH' => ['default' => ''],
            'INLINE_COMMENT' => ['default' => ''],
            'BARE_HASH' => ['default' => ''],
            'EMPTY_VAL' => ['default' => ''],
            'EMPTY_QUOTED' => ['default' => ''],
            'EMPTY_SINGLE' => ['default' => ''],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.complex');

        self::assertSame('localhost', $env->get('DB_HOST'));
        self::assertSame(5432, $env->get('DB_PORT'));
        self::assertSame('my_database', $env->get('DB_NAME'));
        self::assertSame('p@ss w0rd', $env->get('DB_PASS'));
        self::assertSame('value # not a comment', $env->get('QUOTED_HASH'));
        self::assertSame('value', $env->get('INLINE_COMMENT'));
        self::assertSame('color#fff', $env->get('BARE_HASH'));
        self::assertSame('', $env->get('EMPTY_VAL'));
        self::assertSame('', $env->get('EMPTY_QUOTED'));
        self::assertSame('', $env->get('EMPTY_SINGLE'));
    }

    #[Test]
    public function parseListJsonWithSchema(): void
    {
        $schema = [
            'ORIGINS' => ['type' => EnvType::LIST, 'default' => []],
            'METHODS' => ['type' => EnvType::LIST, 'default' => []],
            'SEARCH_PATHS' => ['type' => EnvType::LIST, 'default' => [], 'separator' => ':'],
            'CONFIG' => ['type' => EnvType::JSON, 'default' => []],
            'REPLICAS' => ['type' => EnvType::JSON, 'default' => []],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.list-json');

        $origins = $env->get('ORIGINS');
        self::assertIsArray($origins);
        self::assertCount(3, $origins);
        self::assertSame('http://localhost', $origins[0]);
        self::assertSame('https://example.com', $origins[1]);
        self::assertSame('https://api.example.com', $origins[2]);

        $methods = $env->get('METHODS');
        self::assertIsArray($methods);
        self::assertSame(['GET', 'POST', 'PUT', 'DELETE'], $methods);

        $paths = $env->get('SEARCH_PATHS');
        self::assertIsArray($paths);
        self::assertSame(['/usr/bin', '/usr/local/bin', '/home/bin'], $paths);

        $config = $env->get('CONFIG');
        self::assertIsArray($config);
        self::assertSame(3, $config['max_retries']);
        self::assertSame(30, $config['timeout']);

        $replicas = $env->get('REPLICAS');
        self::assertIsArray($replicas);
        self::assertSame(['replica1.db.com', 'replica2.db.com'], $replicas);
    }

    #[Test]
    public function parseEscapesCorrect(): void
    {
        $schema = [
            'NEWLINE' => ['default' => ''],
            'TAB' => ['default' => ''],
            'BACKSLASH' => ['default' => ''],
            'ESCAPED_QUOTE' => ['default' => ''],
            'ESCAPED_DOLLAR' => ['default' => ''],
            'SINGLE_NO_ESCAPE' => ['default' => ''],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.escapes');

        self::assertSame("hello\nworld", $env->get('NEWLINE'));
        self::assertSame("col1\tcol2", $env->get('TAB'));
        self::assertSame('back\\slash', $env->get('BACKSLASH'));
        self::assertSame('say"hi"', $env->get('ESCAPED_QUOTE'));
        self::assertSame('cost$5', $env->get('ESCAPED_DOLLAR'));
        self::assertSame('hello\\nworld', $env->get('SINGLE_NO_ESCAPE'));
    }

    #[Test]
    public function parseInterpolationReferencesResolved(): void
    {
        $schema = [
            'BASE' => ['default' => ''],
            'DATA_DIR' => ['default' => ''],
            'LOG_DIR' => ['default' => ''],
            'NESTED' => ['default' => ''],
            'LITERAL' => ['default' => ''],
            'MISSING_REF' => ['default' => ''],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.interpolation');

        self::assertSame('/app', $env->get('BASE'));
        self::assertSame('/app/data', $env->get('DATA_DIR'));
        self::assertSame('/app/logs', $env->get('LOG_DIR'));
        self::assertSame('/app/data/cache', $env->get('NESTED'));
        self::assertSame('${BASE}/no-interp', $env->get('LITERAL'));
        self::assertSame('', $env->get('MISSING_REF'));
    }

    #[Test]
    public function parseCircularThrowsParseException(): void
    {
        $schema = [
            'A' => ['default' => ''],
            'B' => ['default' => ''],
            'C' => ['default' => ''],
        ];

        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Circular reference');

        Env::create($schema, self::FIXTURES . '.env.circular');
    }

    #[Test]
    public function parseExportPrefixStripped(): void
    {
        $schema = [
            'FOO' => ['default' => ''],
            'BAZ' => ['default' => ''],
            'NORMAL' => ['default' => ''],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.export');

        self::assertSame('bar', $env->get('FOO'));
        self::assertSame('quoted', $env->get('BAZ'));
        self::assertSame('value', $env->get('NORMAL'));
    }

    #[Test]
    public function parseEmptyFileSchemaDefaultsUsed(): void
    {
        $schema = [
            'APP_NAME' => ['default' => 'DefaultApp'],
            'APP_PORT' => ['type' => EnvType::INT, 'default' => 3000],
        ];

        $env = Env::create($schema, self::FIXTURES . '.env.empty');

        self::assertSame('DefaultApp', $env->get('APP_NAME'));
        self::assertSame(3000, $env->get('APP_PORT'));
    }
}
