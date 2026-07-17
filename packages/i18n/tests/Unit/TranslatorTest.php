<?php

declare(strict_types=1);

namespace PHPdot\I18n\Tests\Unit;

use PHPdot\Contracts\I18n\MessageTranslatorInterface;
use PHPdot\I18n\I18nConfig;
use PHPdot\I18n\Loader\LoaderInterface;
use PHPdot\I18n\Loader\PhpArrayLoader;
use PHPdot\I18n\Tests\Fixtures\ArrayCache;
use PHPdot\I18n\Translator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TranslatorTest extends TestCase
{
    private PhpArrayLoader $loader;

    private ArrayCache $cache;

    private I18nConfig $config;

    protected function setUp(): void
    {
        $this->config = new I18nConfig(
            default: 'en',
            supported: ['en', 'ar'],
            path: __DIR__ . '/../Fixtures/lang',
        );
        $this->loader = new PhpArrayLoader($this->config);
        $this->cache = new ArrayCache();
    }

    private function createTranslator(
        ?LoaderInterface $loader = null,
        ?ArrayCache $cache = null,
        ?I18nConfig $config = null,
    ): Translator {
        return new Translator(
            $loader ?? $this->loader,
            $cache ?? $this->cache,
            $config ?? $this->config,
        );
    }

    // --- contract ---

    #[Test]
    public function implementsMessageTranslatorInterface(): void
    {
        self::assertInstanceOf(MessageTranslatorInterface::class, $this->createTranslator());
    }

    // --- setLocale ---

    #[Test]
    public function setsLocaleLanguageRegionFromFullLocale(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar_JO');

        self::assertSame('ar_JO', $translator->getLocale());
        self::assertSame('ar', $translator->getLanguage());
        self::assertSame('JO', $translator->getRegion());
    }

    #[Test]
    public function setsLocaleWithoutRegion(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar');

        self::assertSame('ar', $translator->getLocale());
        self::assertSame('ar', $translator->getLanguage());
        self::assertSame('', $translator->getRegion());
    }

    #[Test]
    public function fallsBackToDefaultForUnsupportedLanguage(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('fr_FR');

        self::assertSame('en', $translator->getLocale());
        self::assertSame('en', $translator->getLanguage());
        self::assertSame('', $translator->getRegion());
    }

    #[Test]
    public function setLocaleTwiceUpdatesCorrectly(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar_JO');
        $translator->setLocale('ar_SA');

        self::assertSame('ar_SA', $translator->getLocale());
        self::assertSame('SA', $translator->getRegion());
    }

    #[Test]
    public function setLocaleFromSupportedBackToDefault(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar_JO');
        $translator->setLocale('en');

        self::assertSame('en', $translator->getLocale());
        self::assertSame('en', $translator->getLanguage());
        self::assertSame('', $translator->getRegion());
    }

    #[Test]
    public function defaultStateIsDefaultLanguage(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('en', $translator->getLocale());
        self::assertSame('en', $translator->getLanguage());
        self::assertSame('', $translator->getRegion());
    }

    // --- translate ---

    #[Test]
    public function translatesSimpleKeyWithParams(): void
    {
        $translator = $this->createTranslator();
        $result = $translator->translate('messages.welcome', ['name' => 'Omar']);

        self::assertSame('Welcome, Omar!', $result);
    }

    #[Test]
    public function translatesKeyWithoutParams(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('Goodbye!', $translator->translate('messages.goodbye'));
    }

    #[Test]
    public function returnsBracketedPlaceholderForMissingKey(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('[missing.key]', $translator->translate('missing.key'));
    }

    #[Test]
    public function fallsBackToDefaultLanguageForMissingKeyInCurrentLanguage(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar');

        self::assertSame('Page not found', $translator->translate('errors.not_found'));
    }

    #[Test]
    public function fallsBackToDefaultWhenCurrentIsDefaultAndKeyMissing(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('[totally.missing]', $translator->translate('totally.missing'));
    }

    #[Test]
    public function translatesInArabicWhenKeyExistsInArabic(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar');

        self::assertSame('مع السلامة!', $translator->translate('messages.goodbye'));
    }

    #[Test]
    public function translatesWithArabicLocaleAndParams(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar_JO');

        $result = $translator->translate('messages.welcome', ['name' => 'Omar']);
        self::assertSame('مرحباً Omar!', $result);
    }

    #[Test]
    public function icuPluralFormattingWithOne(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('1 item', $translator->translate('messages.items', ['count' => 1]));
    }

    #[Test]
    public function icuPluralFormattingWithMany(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('5 items', $translator->translate('messages.items', ['count' => 5]));
    }

    #[Test]
    public function icuPluralFormattingWithZero(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('0 items', $translator->translate('messages.items', ['count' => 0]));
    }

    #[Test]
    public function icuSelectWithAutoInjectedRegionJO(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('en_JO');

        self::assertSame('Hello from Jordan!', $translator->translate('messages.greeting'));
    }

    #[Test]
    public function icuSelectWithAutoInjectedRegionSA(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('en_SA');

        self::assertSame('Hello from Saudi Arabia!', $translator->translate('messages.greeting'));
    }

    #[Test]
    public function icuSelectWithAutoInjectedRegionOther(): void
    {
        $translator = $this->createTranslator();

        self::assertSame('Hello from the world!', $translator->translate('messages.greeting'));
    }

    #[Test]
    public function autoInjectsLocaleRegionLang(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar_JO');

        // greeting template uses _region_ — if auto-injected, should match JO
        $result = $translator->translate('messages.greeting');
        self::assertSame('Hello from Jordan!', $result);
    }

    #[Test]
    public function userParamsDoNotOverrideAutoInjected(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('en_JO');

        // User passes _region_ = 'SA' but auto-injection should override since it merges after
        // Actually checking the code: params = array_merge($params, auto) — auto wins
        $result = $translator->translate('messages.greeting', ['_region_' => 'SA']);
        self::assertSame('Hello from Jordan!', $result);
    }

    #[Test]
    public function templateWithNoPlaceholdersAndParamsStillWorks(): void
    {
        $translator = $this->createTranslator();

        $result = $translator->translate('messages.goodbye', ['extra' => 'ignored']);
        self::assertSame('Goodbye!', $result);
    }

    #[Test]
    public function returnsRawTemplateWhenFormatterFails(): void
    {
        $template = '{0, date, ::dMMMMyyyy}';

        $loader = new class ($template) implements LoaderInterface {
            public function __construct(private readonly string $template) {}

            public function loadAll(string $language): array
            {
                return ['test.key' => $this->template];
            }
        };

        $translator = new Translator($loader, $this->cache, new I18nConfig(default: 'en', supported: ['en']));
        $result = $translator->translate('test.key', ['wrong' => 'value']);

        self::assertSame($template, $result);
    }

    // --- missing keys ---

    #[Test]
    public function tracksMissingKeys(): void
    {
        $translator = $this->createTranslator();
        $translator->translate('nonexistent.key');

        $missing = $translator->getMissing();

        self::assertArrayHasKey('en', $missing);
        self::assertContains('nonexistent.key', $missing['en']);
    }

    #[Test]
    public function doesNotTrackDuplicateMissingKeys(): void
    {
        $translator = $this->createTranslator();
        $translator->translate('nonexistent.key');
        $translator->translate('nonexistent.key');

        self::assertCount(1, $translator->getMissing()['en']);
    }

    #[Test]
    public function tracksMultipleDifferentMissingKeys(): void
    {
        $translator = $this->createTranslator();
        $translator->translate('missing.one');
        $translator->translate('missing.two');

        $missing = $translator->getMissing()['en'];
        self::assertCount(2, $missing);
        self::assertContains('missing.one', $missing);
        self::assertContains('missing.two', $missing);
    }

    #[Test]
    public function tracksMissingKeysPerLanguage(): void
    {
        $translator = $this->createTranslator();
        $translator->translate('only.in.default.missing');

        $translator->setLocale('ar');
        $translator->translate('ar.only.missing');

        $missing = $translator->getMissing();
        self::assertArrayHasKey('en', $missing);
        self::assertArrayHasKey('ar', $missing);
    }

    #[Test]
    public function getMissingReturnsEmptyBeforeAnyTranslation(): void
    {
        $translator = $this->createTranslator();

        self::assertSame([], $translator->getMissing());
    }

    // --- exposed ---

    // --- exposed: prefix matching (no wildcards) ---

    #[Test]
    public function exposedPrefixMatchesChildrenAndExact(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.buttons']);

        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.buttons.cancel', $exposed);
        self::assertArrayNotHasKey('js.errors.required', $exposed);
    }

    #[Test]
    public function exposedMultiplePrefixes(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.buttons', 'js.errors']);

        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.errors.required', $exposed);
    }

    #[Test]
    public function exposedExactKeyMatch(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.buttons.save']);

        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertCount(1, $exposed);
    }

    #[Test]
    public function exposedDoesNotMatchPartialSegment(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['message']);

        self::assertArrayNotHasKey('messages.welcome', $exposed);
    }

    #[Test]
    public function exposedReturnsEmptyForEmptyPatterns(): void
    {
        $translator = $this->createTranslator();

        self::assertSame([], $translator->exposed([]));
    }

    // --- exposed: language merging ---

    #[Test]
    public function exposedMergesCurrentAndDefaultLanguage(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar');

        $exposed = $translator->exposed(['messages']);

        self::assertArrayHasKey('messages.welcome', $exposed);
        self::assertArrayHasKey('messages.items', $exposed);
    }

    #[Test]
    public function exposedCurrentLanguageWinsOverDefault(): void
    {
        $translator = $this->createTranslator();
        $translator->setLocale('ar');

        $exposed = $translator->exposed(['messages']);

        self::assertSame('مرحباً {name}!', $exposed['messages.welcome']);
    }

    #[Test]
    public function exposedDefaultLanguageDoesNotDuplicate(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['messages']);

        self::assertArrayHasKey('messages.welcome', $exposed);
    }

    // --- exposed: single-segment wildcard (*) ---

    #[Test]
    public function exposedSingleWildcardMatchesOneSegment(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.*.save']);

        // js.buttons.save matches — * = buttons
        self::assertArrayHasKey('js.buttons.save', $exposed);
        // js.buttons.cancel does NOT match — last segment is cancel, not save
        self::assertArrayNotHasKey('js.buttons.cancel', $exposed);
    }

    #[Test]
    public function exposedWildcardAtEnd(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.buttons.*']);

        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.buttons.cancel', $exposed);
        // Does NOT match js.errors.required — first segment after js is errors, not buttons
        self::assertArrayNotHasKey('js.errors.required', $exposed);
    }

    #[Test]
    public function exposedWildcardAtStart(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['*.welcome']);

        // messages.welcome matches — * = messages
        self::assertArrayHasKey('messages.welcome', $exposed);
        // errors.not_found does NOT match
        self::assertArrayNotHasKey('errors.not_found', $exposed);
    }

    #[Test]
    public function exposedWildcardDoesNotMatchMultipleSegments(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.*']);

        // js.buttons.save has 3 segments — js.* expects exactly 2
        self::assertArrayNotHasKey('js.buttons.save', $exposed);
    }

    #[Test]
    public function exposedMultipleWildcards(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['*.*.*']);

        // All 3-segment keys match
        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.buttons.cancel', $exposed);
        self::assertArrayHasKey('js.errors.required', $exposed);
        // 2-segment keys do NOT match
        self::assertArrayNotHasKey('messages.welcome', $exposed);
    }

    // --- exposed: recursive wildcard (**) ---

    #[Test]
    public function exposedDoubleStarMatchesAll(): void
    {
        $translator = $this->createTranslator();
        $all = $this->loader->loadAll('en');
        $exposed = $translator->exposed(['**']);

        // Should return all translations
        foreach ($all as $key => $value) {
            self::assertArrayHasKey($key, $exposed);
        }
    }

    #[Test]
    public function exposedPrefixDoubleStarMatchesAllDescendants(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['js.**']);

        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.buttons.cancel', $exposed);
        self::assertArrayHasKey('js.errors.required', $exposed);
        // Non-js keys excluded
        self::assertArrayNotHasKey('messages.welcome', $exposed);
        self::assertArrayNotHasKey('errors.not_found', $exposed);
    }

    #[Test]
    public function exposedDoubleStarMatchesMultipleDepths(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['messages.**']);

        // 2-segment: messages.welcome, messages.goodbye, etc.
        self::assertArrayHasKey('messages.welcome', $exposed);
        self::assertArrayHasKey('messages.goodbye', $exposed);
    }

    #[Test]
    public function exposedDoubleStarDoesNotMatchPrefixItself(): void
    {
        $translator = $this->createTranslator();
        // js.** should match js.X but NOT a key literally called "js"
        // Since we don't have a key "js", just verify descendants are matched
        $exposed = $translator->exposed(['js.**']);

        self::assertNotEmpty($exposed);

        foreach (array_keys($exposed) as $key) {
            self::assertStringStartsWith('js.', $key);
        }
    }

    // --- exposed: mixed patterns ---

    #[Test]
    public function exposedMixedPatternsAndPrefixes(): void
    {
        $translator = $this->createTranslator();
        $exposed = $translator->exposed(['messages.welcome', 'js.buttons.*']);

        // Exact match
        self::assertArrayHasKey('messages.welcome', $exposed);
        // Wildcard match
        self::assertArrayHasKey('js.buttons.save', $exposed);
        self::assertArrayHasKey('js.buttons.cancel', $exposed);
        // Not matched
        self::assertArrayNotHasKey('messages.goodbye', $exposed);
    }

    // --- caching ---

    #[Test]
    public function loaderCalledOncePerLanguageThenInMemory(): void
    {
        $callCount = 0;
        $realLoader = $this->loader;

        $countingLoader = new class ($callCount, $realLoader) implements LoaderInterface {
            public function __construct(private int &$count, private LoaderInterface $inner) {}

            public function loadAll(string $language): array
            {
                $this->count++;
                return $this->inner->loadAll($language);
            }
        };

        $translator = new Translator($countingLoader, $this->cache, new I18nConfig(default: 'en', supported: ['en', 'ar'], path: __DIR__ . '/../Fixtures/lang'));

        $translator->translate('messages.welcome', ['name' => 'A']);
        $translator->translate('messages.goodbye');
        $translator->translate('messages.welcome', ['name' => 'B']);

        self::assertSame(1, $callCount);
    }

    #[Test]
    public function psr16CacheIsUsedBetweenInstances(): void
    {
        $callCount = 0;
        $realLoader = $this->loader;

        $countingLoader = new class ($callCount, $realLoader) implements LoaderInterface {
            public function __construct(private int &$count, private LoaderInterface $inner) {}

            public function loadAll(string $language): array
            {
                $this->count++;
                return $this->inner->loadAll($language);
            }
        };

        $t1 = new Translator($countingLoader, $this->cache, new I18nConfig(default: 'en', supported: ['en'], path: __DIR__ . '/../Fixtures/lang'));
        $t1->translate('messages.welcome', ['name' => 'A']);
        self::assertSame(1, $callCount);

        // Second instance shares the same PSR-16 cache
        $t2 = new Translator($countingLoader, $this->cache, new I18nConfig(default: 'en', supported: ['en'], path: __DIR__ . '/../Fixtures/lang'));
        $t2->translate('messages.goodbye');
        // Loader should NOT be called again — PSR-16 cache hit
        self::assertSame(1, $callCount);
    }

    #[Test]
    public function clearCacheForSpecificLanguageReloads(): void
    {
        $callCount = 0;
        $realLoader = $this->loader;

        $countingLoader = new class ($callCount, $realLoader) implements LoaderInterface {
            public function __construct(private int &$count, private LoaderInterface $inner) {}

            public function loadAll(string $language): array
            {
                $this->count++;
                return $this->inner->loadAll($language);
            }
        };

        $translator = new Translator($countingLoader, $this->cache, new I18nConfig(default: 'en', supported: ['en', 'ar'], path: __DIR__ . '/../Fixtures/lang'));
        $translator->translate('messages.welcome', ['name' => 'A']);
        self::assertSame(1, $callCount);

        $translator->clearCache('en');
        $translator->translate('messages.welcome', ['name' => 'B']);

        self::assertSame(2, $callCount);
    }

    #[Test]
    public function clearCacheAllReloadsAllLanguages(): void
    {
        $callCount = 0;
        $realLoader = $this->loader;

        $countingLoader = new class ($callCount, $realLoader) implements LoaderInterface {
            public function __construct(private int &$count, private LoaderInterface $inner) {}

            public function loadAll(string $language): array
            {
                $this->count++;
                return $this->inner->loadAll($language);
            }
        };

        $translator = new Translator($countingLoader, $this->cache, new I18nConfig(default: 'en', supported: ['en', 'ar'], path: __DIR__ . '/../Fixtures/lang'));

        $translator->translate('messages.welcome', ['name' => 'A']);
        $translator->setLocale('ar');
        $translator->translate('messages.welcome', ['name' => 'B']);
        self::assertSame(2, $callCount);

        $translator->clearCache();

        $translator->translate('messages.welcome', ['name' => 'C']);
        $translator->setLocale('en');
        $translator->translate('messages.welcome', ['name' => 'D']);

        self::assertSame(4, $callCount);
    }

    #[Test]
    public function clearCacheForNonLoadedLanguageDoesNotError(): void
    {
        $translator = $this->createTranslator();
        $translator->clearCache('ar');

        // Should not throw
        self::assertTrue(true);
    }

    // --- getters ---

    #[Test]
    public function getDefaultReturnsDefault(): void
    {
        $translator = $this->createTranslator(config: new I18nConfig(default: 'en'));

        self::assertSame('en', $translator->getDefault());
    }

    #[Test]
    public function getSupportedReturnsSupportedList(): void
    {
        $translator = $this->createTranslator(config: new I18nConfig(supported: ['en', 'ar', 'fr']));

        self::assertSame(['en', 'ar', 'fr'], $translator->getSupported());
    }

    #[Test]
    public function isSupportedReturnsTrueForSupported(): void
    {
        $translator = $this->createTranslator();

        self::assertTrue($translator->isSupported('en'));
        self::assertTrue($translator->isSupported('ar'));
    }

    #[Test]
    public function isSupportedReturnsFalseForUnsupported(): void
    {
        $translator = $this->createTranslator();

        self::assertFalse($translator->isSupported('fr'));
        self::assertFalse($translator->isSupported('de'));
    }

    #[Test]
    public function isSupportedIsCaseSensitive(): void
    {
        $translator = $this->createTranslator();

        self::assertFalse($translator->isSupported('EN'));
        self::assertFalse($translator->isSupported('Ar'));
    }
}
