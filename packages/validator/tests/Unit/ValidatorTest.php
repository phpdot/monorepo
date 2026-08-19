<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Error\ErrorBag;
use PHPdot\Validator\Exception\InvalidRuleException;
use PHPdot\Validator\Exception\MissingErrorCodeException;
use PHPdot\Validator\Rule\Bail;
use PHPdot\Validator\Rule\Nullable;
use PHPdot\Validator\Rule\Sometimes;
use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPdot\Validator\Tests\Stubs\AlwaysPasses;
use PHPdot\Validator\Tests\Stubs\CapturingRule;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(new ErrorBag());
    }

    #[Test]
    public function noRulesProducesEmptyBag(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], []);

        self::assertFalse($bag->hasErrors());
        self::assertCount(0, $bag);
    }

    #[Test]
    public function passingRuleDoesNotAddError(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], [
            'email' => [(new AlwaysPasses())->withError(TestErrorCode::Generic)],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function failingRuleAddsErrorWithFieldContext(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], [
            'email' => [(new AlwaysFails())->withError(TestErrorCode::EmailInvalid)],
        ]);

        self::assertTrue($bag->hasErrors());
        self::assertCount(1, $bag);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame(TestErrorCode::EmailInvalid->value, $entry->code);
        self::assertSame('email', $entry->context);
        self::assertSame('Please enter a valid email address.', $entry->message);
    }

    #[Test]
    public function failingRuleCarriesParamsForInterpolation(): void
    {
        $rule = (new CapturingRule(['min' => 3, 'max' => 50]))
            ->withError(TestErrorCode::UsernameTooShort);

        $bag = $this->validator->validate(['username' => 'ab'], [
            'username' => [$rule],
        ]);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame(['field' => 'username', 'min' => 3, 'max' => 50], $entry->params);
    }

    #[Test]
    public function withErrorParamsReachTheEntry(): void
    {
        // AlwaysFails has no params of its own — the call site is the only source.
        $rule = (new AlwaysFails())->withError(TestErrorCode::UsernameTooShort, ['min' => 1, 'max' => 4]);

        $bag = $this->validator->validate(['prefixes' => 'x'], [
            'prefixes' => [$rule],
        ]);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame(['field' => 'prefixes', 'min' => 1, 'max' => 4], $entry->params);
    }

    #[Test]
    public function withErrorParamsOverrideTheRulesOwn(): void
    {
        $rule = (new CapturingRule(['min' => 3, 'max' => 50]))
            ->withError(TestErrorCode::UsernameTooShort, ['max' => 5]);

        $bag = $this->validator->validate(['username' => 'ab'], [
            'username' => [$rule],
        ]);

        $entry = $bag->first();
        self::assertNotNull($entry);
        self::assertSame(['field' => 'username', 'min' => 3, 'max' => 5], $entry->params);
    }

    #[Test]
    public function multipleFailingRulesAccumulateErrors(): void
    {
        $bag = $this->validator->validate(['email' => 'bad'], [
            'email' => [
                (new AlwaysFails())->withError(TestErrorCode::EmailRequired),
                (new AlwaysFails())->withError(TestErrorCode::EmailInvalid),
            ],
        ]);

        self::assertCount(2, $bag);
        self::assertSame(TestErrorCode::EmailRequired->value, $bag->all()[0]->code);
        self::assertSame(TestErrorCode::EmailInvalid->value, $bag->all()[1]->code);
    }

    #[Test]
    public function errorsForMultipleFieldsUseCorrectContext(): void
    {
        $bag = $this->validator->validate([
            'email' => 'bad',
            'username' => 'x',
        ], [
            'email' => [(new AlwaysFails())->withError(TestErrorCode::EmailInvalid)],
            'username' => [(new AlwaysFails())->withError(TestErrorCode::UsernameTooShort)],
        ]);

        self::assertCount(1, $bag->forContext('email'));
        self::assertCount(1, $bag->forContext('username'));
    }

    #[Test]
    public function failingRuleWithoutErrorCodeThrows(): void
    {
        try {
            $this->validator->validate(['email' => 'bad'], [
                'email' => [new AlwaysFails()],
            ]);
            self::fail('Expected MissingErrorCodeException.');
        } catch (MissingErrorCodeException $e) {
            self::assertSame('email', $e->field);
            self::assertSame(AlwaysFails::class, $e->ruleClass);
        }
    }

    #[Test]
    public function passingRuleWithoutErrorCodeDoesNotThrow(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], [
            'email' => [new AlwaysPasses()],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function nonRuleInListThrows(): void
    {
        try {
            // @phpstan-ignore-next-line — intentionally invalid input
            $this->validator->validate(['email' => 'a@b.com'], [
                'email' => ['required|email'],
            ]);
            self::fail('Expected InvalidRuleException.');
        } catch (InvalidRuleException $e) {
            self::assertSame('email', $e->field);
            self::assertSame('string', $e->actualType);
        }
    }

    #[Test]
    public function sometimesSkipsChainWhenFieldAbsent(): void
    {
        $bag = $this->validator->validate([], [
            'phone' => [
                new Sometimes(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function sometimesDoesNotSkipWhenFieldPresent(): void
    {
        $bag = $this->validator->validate(['phone' => '123'], [
            'phone' => [
                new Sometimes(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertTrue($bag->hasErrors());
    }

    #[Test]
    public function nullableSkipsChainWhenValueIsNull(): void
    {
        $bag = $this->validator->validate(['phone' => null], [
            'phone' => [
                new Nullable(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function nullableDoesNotSkipWhenValueIsNotNull(): void
    {
        $bag = $this->validator->validate(['phone' => '123'], [
            'phone' => [
                new Nullable(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertTrue($bag->hasErrors());
    }

    #[Test]
    public function validationContextReceivesFullPayload(): void
    {
        $rule = (new CapturingRule())->withError(TestErrorCode::Generic);

        $this->validator->validate(['a' => 1, 'b' => 2], [
            'a' => [$rule],
        ]);

        self::assertNotNull($rule->lastContext);
        self::assertSame(['a' => 1, 'b' => 2], $rule->lastContext->all());
        self::assertSame('a', $rule->lastContext->field());
        self::assertSame(1, $rule->lastValue);
    }

    #[Test]
    public function bailAtStartStopsChainOnFirstFailure(): void
    {
        $bag = $this->validator->validate(['x' => 1], [
            'x' => [
                new Bail(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertCount(1, $bag);
    }

    #[Test]
    public function bailAtEndStopsChainOnFirstFailure(): void
    {
        $bag = $this->validator->validate(['x' => 1], [
            'x' => [
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                new Bail(),
            ],
        ]);

        self::assertCount(1, $bag);
    }

    #[Test]
    public function bailInMiddleIsPositionIndependent(): void
    {
        $bag = $this->validator->validate(['x' => 1], [
            'x' => [
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                new Bail(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertCount(1, $bag);
    }

    #[Test]
    public function bailDoesNotStopWhenRulesPass(): void
    {
        $bag = $this->validator->validate(['x' => 1], [
            'x' => [
                new Bail(),
                (new AlwaysPasses())->withError(TestErrorCode::Generic),
                (new AlwaysPasses())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function withoutBailChainRunsAllRules(): void
    {
        $bag = $this->validator->validate(['x' => 1], [
            'x' => [
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertCount(3, $bag);
    }
}
