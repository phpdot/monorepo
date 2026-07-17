<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit;

use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Env;
use PHPdot\Env\Exception\SchemaException;
use PHPdot\Env\Exception\ValidationException;
use PHPdot\Env\Schema\EnvSchema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/';

    #[Test]
    public function createFromFixtureFileAndSchema(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame('TestApp', $env->get('APP_NAME'));
    }

    #[Test]
    public function getReturnsTypedInt(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame(8080, $env->get('APP_PORT'));
    }

    #[Test]
    public function getReturnsTypedBool(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertTrue($env->get('APP_DEBUG'));
    }

    #[Test]
    public function getReturnsTypedFloat(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame(1.5, $env->get('RATE_LIMIT'));
    }

    #[Test]
    public function getReturnsTypedString(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame('localhost', $env->get('DB_HOST'));
    }

    #[Test]
    public function getUnknownKeyThrowsSchemaException(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        $this->expectException(SchemaException::class);
        $env->get('NONEXISTENT');
    }

    #[Test]
    public function hasReturnsTrueForSetValues(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertTrue($env->has('APP_NAME'));
    }

    #[Test]
    public function hasReturnsFalseForDefaultsOnly(): void
    {
        $env = Env::createForTesting(
            ['FOO' => ['default' => 'bar']],
        );

        self::assertFalse($env->has('FOO'));
    }

    #[Test]
    public function allReturnsAllTypedValues(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        $all = $env->all();

        self::assertArrayHasKey('APP_NAME', $all);
        self::assertArrayHasKey('APP_PORT', $all);
        self::assertArrayHasKey('APP_DEBUG', $all);
        self::assertArrayHasKey('RATE_LIMIT', $all);
        self::assertArrayHasKey('DB_HOST', $all);
    }

    #[Test]
    public function allMaskedMasksSensitiveValues(): void
    {
        $schema = [
            'SECRET' => ['sensitive' => true, 'default' => 'x'],
            'PUBLIC' => ['default' => 'y'],
        ];

        $env = Env::createForTesting($schema, ['SECRET' => 'my-secret', 'PUBLIC' => 'visible']);

        $masked = $env->allMasked();

        self::assertSame('***', $masked['SECRET']);
        self::assertSame('visible', $masked['PUBLIC']);
    }

    #[Test]
    public function getRawReturnsRawString(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertSame('8080', $env->getRaw('APP_PORT'));
    }

    #[Test]
    public function getRawReturnsNullForMissing(): void
    {
        $env = Env::createForTesting(
            ['FOO' => ['default' => 'bar']],
        );

        self::assertNull($env->getRaw('FOO'));
    }

    #[Test]
    public function getSchemaReturnsSchema(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        self::assertInstanceOf(EnvSchema::class, $env->getSchema());
    }

    #[Test]
    public function getLoadedFilesReturnsPaths(): void
    {
        $path = self::FIXTURES . '.env.basic';
        $env = Env::create(self::FIXTURES . 'schema.basic.php', $path);

        $files = $env->getLoadedFiles();

        self::assertCount(1, $files);
        self::assertSame($path, $files[0]);
    }

    #[Test]
    public function createWithMissingRequiredKeyThrowsValidationException(): void
    {
        $schema = [
            'REQUIRED_KEY' => ['required' => true],
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Required env keys missing');
        Env::createForTesting($schema);
    }

    #[Test]
    public function safeCreateSkipsMissingFiles(): void
    {
        $env = Env::safeCreate(
            self::FIXTURES . 'schema.basic.php',
            [self::FIXTURES . '.env.basic', self::FIXTURES . '.env.nonexistent'],
        );

        self::assertSame('TestApp', $env->get('APP_NAME'));
        self::assertCount(1, $env->getLoadedFiles());
    }

    #[Test]
    public function createForTestingWorksWithoutFiles(): void
    {
        $env = Env::createForTesting(
            ['NAME' => ['default' => 'test']],
            ['NAME' => 'overridden'],
        );

        self::assertSame('overridden', $env->get('NAME'));
        self::assertSame([], $env->getLoadedFiles());
    }

    #[Test]
    public function parseStringReturnsRawKeyValuePairs(): void
    {
        $result = Env::parseString("FOO=bar\nBAZ=qux\n");

        self::assertSame('bar', $result['FOO']);
        self::assertSame('qux', $result['BAZ']);
    }

    #[Test]
    public function compileAndCreateFromCacheRoundTrip(): void
    {
        $env = Env::create(
            self::FIXTURES . 'schema.basic.php',
            self::FIXTURES . '.env.basic',
        );

        $cachePath = sys_get_temp_dir() . '/env_test_cache_' . uniqid() . '.php';

        try {
            $env->compile($cachePath);

            $cached = Env::createFromCache(
                self::FIXTURES . 'schema.basic.php',
                $cachePath,
            );

            self::assertSame($env->get('APP_NAME'), $cached->get('APP_NAME'));
            self::assertSame($env->get('APP_PORT'), $cached->get('APP_PORT'));
            self::assertSame($env->get('APP_DEBUG'), $cached->get('APP_DEBUG'));
        } finally {
            if (is_file($cachePath)) {
                unlink($cachePath);
            }
        }
    }

    #[Test]
    public function multiFileLoadingLaterFileOverrides(): void
    {
        $schema = [
            'APP_ENV' => ['default' => 'production'],
            'DB_HOST' => ['default' => 'localhost'],
            'DB_PORT' => ['type' => EnvType::INT, 'default' => 5432],
        ];

        $env = Env::create($schema, [
            self::FIXTURES . '.env.override-base',
            self::FIXTURES . '.env.override-local',
        ]);

        self::assertSame('development', $env->get('APP_ENV'));
        self::assertSame('localhost', $env->get('DB_HOST'));
        self::assertSame(5432, $env->get('DB_PORT'));
    }

    #[Test]
    public function crossFileInterpolationWorks(): void
    {
        $baseContent = "BASE=/app\n";
        $localContent = "DATA_DIR=\${BASE}/data\n";

        $basePath = sys_get_temp_dir() . '/env_test_base_' . uniqid();
        $localPath = sys_get_temp_dir() . '/env_test_local_' . uniqid();

        try {
            file_put_contents($basePath, $baseContent);
            file_put_contents($localPath, $localContent);

            $schema = [
                'BASE' => ['default' => '/default'],
                'DATA_DIR' => ['default' => '/default/data'],
            ];

            $env = Env::create($schema, [$basePath, $localPath]);

            self::assertSame('/app', $env->get('BASE'));
            self::assertSame('/app/data', $env->get('DATA_DIR'));
        } finally {
            if (is_file($basePath)) {
                unlink($basePath);
            }
            if (is_file($localPath)) {
                unlink($localPath);
            }
        }
    }
}
