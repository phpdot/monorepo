<?php

declare(strict_types=1);

namespace PHPdot\I18n\Tests\Unit\Loader;

use PHPdot\I18n\I18nConfig;
use PHPdot\I18n\Loader\JsonLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonLoaderTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = __DIR__ . '/../../Fixtures/lang_json';
    }

    private function createLoader(?string $path = null): JsonLoader
    {
        return new JsonLoader(new I18nConfig(path: $path ?? $this->basePath));
    }

    #[Test]
    public function loadsEnglishMessagesWithPrefixedKeys(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('en');

        self::assertArrayHasKey('messages.welcome', $translations);
        self::assertSame('Welcome, {name}!', $translations['messages.welcome']);
        self::assertArrayHasKey('messages.goodbye', $translations);
    }

    #[Test]
    public function loadsEnglishErrors(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('en');

        self::assertArrayHasKey('errors.not_found', $translations);
        self::assertSame('Page not found', $translations['errors.not_found']);
    }

    #[Test]
    public function loadsArabicMessages(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('ar');

        self::assertArrayHasKey('messages.welcome', $translations);
        self::assertSame('مرحباً {name}!', $translations['messages.welcome']);
    }

    #[Test]
    public function mergesMultipleJsonFiles(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('en');

        self::assertArrayHasKey('messages.welcome', $translations);
        self::assertArrayHasKey('errors.not_found', $translations);
    }

    #[Test]
    public function returnsEmptyForNonExistentLanguage(): void
    {
        $loader = $this->createLoader();

        self::assertSame([], $loader->loadAll('fr'));
    }

    #[Test]
    public function returnsEmptyForNonExistentBasePath(): void
    {
        $loader = $this->createLoader('/non/existent/path');

        self::assertSame([], $loader->loadAll('en'));
    }

    #[Test]
    public function keysAreSorted(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('en');

        $keys = array_keys($translations);
        $sorted = $keys;
        sort($sorted);

        self::assertSame($sorted, $keys);
    }

    #[Test]
    public function allValuesAreStrings(): void
    {
        $loader = $this->createLoader();
        $translations = $loader->loadAll('en');

        foreach ($translations as $key => $value) {
            self::assertIsString($value, "Value for key '{$key}' should be string");
        }
    }
}
