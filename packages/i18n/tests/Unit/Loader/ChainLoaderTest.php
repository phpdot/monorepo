<?php

declare(strict_types=1);

namespace PHPdot\I18n\Tests\Unit\Loader;

use PHPdot\I18n\I18nConfig;
use PHPdot\I18n\Loader\ChainLoader;
use PHPdot\I18n\Loader\JsonLoader;
use PHPdot\I18n\Loader\LoaderInterface;
use PHPdot\I18n\Loader\PhpArrayLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChainLoaderTest extends TestCase
{
    #[Test]
    public function mergesFromMultipleLoaders(): void
    {
        $phpLoader = new PhpArrayLoader(new I18nConfig(path: __DIR__ . '/../../Fixtures/lang'));
        $jsonLoader = new JsonLoader(new I18nConfig(path: __DIR__ . '/../../Fixtures/lang_json'));

        $chain = new ChainLoader([$phpLoader, $jsonLoader]);
        $translations = $chain->loadAll('en');

        self::assertArrayHasKey('errors.not_found', $translations);
        self::assertArrayHasKey('messages.welcome', $translations);
    }

    #[Test]
    public function lastLoaderWinsForDuplicateKeys(): void
    {
        $first = new class implements LoaderInterface {
            public function loadAll(string $language): array
            {
                return ['greet' => 'Hello from first', 'unique_first' => 'only in first'];
            }
        };

        $second = new class implements LoaderInterface {
            public function loadAll(string $language): array
            {
                return ['greet' => 'Hello from second', 'unique_second' => 'only in second'];
            }
        };

        $chain = new ChainLoader([$first, $second]);
        $translations = $chain->loadAll('en');

        self::assertSame('Hello from second', $translations['greet']);
        self::assertSame('only in first', $translations['unique_first']);
        self::assertSame('only in second', $translations['unique_second']);
    }

    #[Test]
    public function threeLoadersLastWins(): void
    {
        $make = static fn(string $val) => new class ($val) implements LoaderInterface {
            public function __construct(private readonly string $val) {}

            public function loadAll(string $language): array
            {
                return ['key' => $this->val];
            }
        };

        $chain = new ChainLoader([$make('first'), $make('second'), $make('third')]);

        self::assertSame('third', $chain->loadAll('en')['key']);
    }

    #[Test]
    public function singleLoaderWorks(): void
    {
        $loader = new PhpArrayLoader(new I18nConfig(path: __DIR__ . '/../../Fixtures/lang'));
        $chain = new ChainLoader([$loader]);

        self::assertArrayHasKey('messages.welcome', $chain->loadAll('en'));
    }

    #[Test]
    public function emptyLoadersReturnsEmpty(): void
    {
        $chain = new ChainLoader([]);

        self::assertSame([], $chain->loadAll('en'));
    }

    #[Test]
    public function allLoadersReturnEmptyStillWorks(): void
    {
        $empty = new class implements LoaderInterface {
            public function loadAll(string $language): array
            {
                return [];
            }
        };

        $chain = new ChainLoader([$empty, $empty]);

        self::assertSame([], $chain->loadAll('en'));
    }

    #[Test]
    public function passesLanguageToAllLoaders(): void
    {
        $languages = [];

        $tracker = new class ($languages) implements LoaderInterface {
            /** @param list<string> $languages */
            public function __construct(private array &$languages) {}

            public function loadAll(string $language): array
            {
                $this->languages[] = $language;
                return [];
            }
        };

        $chain = new ChainLoader([$tracker, $tracker]);
        $chain->loadAll('ar');

        self::assertSame(['ar', 'ar'], $languages);
    }
}
