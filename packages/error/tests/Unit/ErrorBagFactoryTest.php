<?php

declare(strict_types=1);

namespace PHPdot\Error\Tests\Unit;

use PHPdot\Contracts\I18n\MessageTranslatorInterface;
use PHPdot\Error\ErrorBag;
use PHPdot\Error\ErrorBagFactory;
use PHPdot\Error\Tests\Fixtures\UserErrors;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorBagFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_fresh_bag_each_call(): void
    {
        $factory = new ErrorBagFactory();

        $bag1 = $factory->create();
        $bag2 = $factory->create();

        self::assertInstanceOf(ErrorBag::class, $bag1);
        self::assertInstanceOf(ErrorBag::class, $bag2);
        self::assertNotSame($bag1, $bag2);
    }

    #[Test]
    public function created_bag_has_no_translator_when_factory_built_without_one(): void
    {
        $factory = new ErrorBagFactory();
        $bag = $factory->create();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        $entry = $bag->first();
        self::assertNotNull($entry);
        // raw key, no translation applied
        self::assertSame('errors.user.not_found', $entry->description);
    }

    #[Test]
    public function created_bag_uses_factory_translator(): void
    {
        $translator = new class implements MessageTranslatorInterface {
            public function translate(string $key, array $params = []): string
            {
                return 'TRANSLATED:' . $key;
            }
        };

        $factory = new ErrorBagFactory($translator);
        $bag = $factory->create();
        $bag->add(UserErrors::NOT_FOUND, 'user_id');

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame('TRANSLATED:errors.user.not_found', $entry->description);
    }
}
