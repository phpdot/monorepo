<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Integration;

use PHPdot\Env\Enum\EnvType;
use PHPdot\Env\Env;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MultiFileTest extends TestCase
{
    private const string FIXTURES = __DIR__ . '/../Fixtures/';

    #[Test]
    public function localOverridesBase(): void
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
    public function baseValuesPreservedWhenNotOverridden(): void
    {
        $schema = [
            'APP_ENV' => ['default' => 'production'],
            'DB_HOST' => ['default' => 'localhost'],
            'DB_PORT' => ['type' => EnvType::INT, 'default' => 3306],
        ];

        $env = Env::create($schema, [
            self::FIXTURES . '.env.override-base',
            self::FIXTURES . '.env.override-local',
        ]);

        // DB_PORT is only in base, should be preserved
        self::assertSame(5432, $env->get('DB_PORT'));
    }

    #[Test]
    public function loadedFilesReflectsAllFiles(): void
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

        self::assertCount(2, $env->getLoadedFiles());
    }
}
