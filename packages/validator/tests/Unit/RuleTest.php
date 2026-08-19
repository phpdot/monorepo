<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPdot\Validator\Tests\Stubs\CapturingRule;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    #[Test]
    public function codeIsNullUntilWithErrorCalled(): void
    {
        $rule = new AlwaysFails();

        self::assertNull($rule->code());
    }

    #[Test]
    public function withErrorReturnsNewInstance(): void
    {
        $rule = new AlwaysFails();
        $bound = $rule->withError(TestErrorCode::Generic);

        self::assertNotSame($rule, $bound);
        self::assertNull($rule->code());
        self::assertSame(TestErrorCode::Generic, $bound->code());
    }

    #[Test]
    public function withErrorCanBeCalledRepeatedlyReturningANewInstance(): void
    {
        $rule = new AlwaysFails();
        $first = $rule->withError(TestErrorCode::Generic);
        $second = $first->withError(TestErrorCode::EmailRequired);

        self::assertSame(TestErrorCode::Generic, $first->code());
        self::assertSame(TestErrorCode::EmailRequired, $second->code());
    }

    #[Test]
    public function defaultParamsContainField(): void
    {
        $rule = new AlwaysFails();
        $context = new ValidationContext('email', []);

        self::assertSame(['field' => 'email'], $rule->params($context));
    }

    #[Test]
    public function subclassCanExtendParams(): void
    {
        $rule = new CapturingRule(['min' => 3, 'max' => 50]);
        $context = new ValidationContext('username', []);

        self::assertSame(
            ['field' => 'username', 'min' => 3, 'max' => 50],
            $rule->params($context),
        );
    }

    #[Test]
    public function errorParamsDefaultToEmpty(): void
    {
        $rule = new AlwaysFails();

        self::assertSame([], $rule->errorParams());
    }

    #[Test]
    public function withErrorCarriesParams(): void
    {
        $rule = (new AlwaysFails())->withError(TestErrorCode::Generic, ['min' => 1, 'max' => 4]);

        self::assertSame(['min' => 1, 'max' => 4], $rule->errorParams());
    }

    #[Test]
    public function withErrorParamsDoNotLeakToTheOriginalRule(): void
    {
        $rule = new AlwaysFails();
        $bound = $rule->withError(TestErrorCode::Generic, ['max' => 4]);

        self::assertSame([], $rule->errorParams());
        self::assertSame(['max' => 4], $bound->errorParams());
    }

    #[Test]
    public function errorParamsStayOutOfParams(): void
    {
        $rule = (new AlwaysFails())->withError(TestErrorCode::Generic, ['max' => 4]);
        $context = new ValidationContext('prefixes', []);

        // The validator merges the two; params() itself stays the rule's own.
        self::assertSame(['field' => 'prefixes'], $rule->params($context));
    }
}
