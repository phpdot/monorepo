<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Contracts\I18n\MessageTranslatorInterface;
use PHPdot\Error\ErrorBagFactory;
use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\Validator;
use PHPdot\Validator\ValidatorFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidatorFactoryTest extends TestCase
{
    #[Test]
    public function it_creates_fresh_validator_each_call(): void
    {
        $factory = new ValidatorFactory();

        $v1 = $factory->create();
        $v2 = $factory->create();

        self::assertInstanceOf(Validator::class, $v1);
        self::assertInstanceOf(Validator::class, $v2);
        self::assertNotSame($v1, $v2);
    }

    #[Test]
    public function each_created_validator_has_its_own_bag(): void
    {
        $factory = new ValidatorFactory();

        $v1 = $factory->create();
        $v2 = $factory->create();

        $v1->validate(['x' => 1], ['x' => [(new AlwaysFails())->withError(TestErrorCode::Generic)]]);

        self::assertCount(1, $v1->errors());
        self::assertCount(0, $v2->errors());
    }

    #[Test]
    public function multi_payload_accumulates_into_one_bag(): void
    {
        $factory = new ValidatorFactory();
        $v = $factory->create();

        $v->validate(['x' => 1], ['x' => [(new AlwaysFails())->withError(TestErrorCode::Generic)]]);
        $v->validate(['y' => 2], ['y' => [(new AlwaysFails())->withError(TestErrorCode::Generic)]]);

        self::assertCount(2, $v->errors());
    }

    #[Test]
    public function created_validator_uses_bag_factorys_translator(): void
    {
        $translator = new class implements MessageTranslatorInterface {
            public function translate(string $key, array $params = []): string
            {
                return 'TRANSLATED:' . $key;
            }
        };

        $factory = new ValidatorFactory(new ErrorBagFactory($translator));
        $v = $factory->create();

        $v->validate(['x' => 1], ['x' => [(new AlwaysFails())->withError(TestErrorCode::Generic)]]);

        $entry = $v->errors()->first();
        self::assertNotNull($entry);
        self::assertStringStartsWith('TRANSLATED:', $entry->description);
    }
}
