<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit;

use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPdot\Validator\Tests\Stubs\CapturingRule;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class RuleTest extends TestCase
{
    public function test_code_is_null_until_with_error_called(): void
    {
        $rule = new AlwaysFails();

        self::assertNull($rule->code());
    }

    public function test_with_error_returns_new_instance(): void
    {
        $rule = new AlwaysFails();
        $bound = $rule->withError(TestErrorCode::Generic);

        self::assertNotSame($rule, $bound);
        self::assertNull($rule->code());
        self::assertSame(TestErrorCode::Generic, $bound->code());
    }

    public function test_with_error_can_be_called_repeatedly_returning_a_new_instance(): void
    {
        $rule = new AlwaysFails();
        $first = $rule->withError(TestErrorCode::Generic);
        $second = $first->withError(TestErrorCode::EmailRequired);

        self::assertSame(TestErrorCode::Generic, $first->code());
        self::assertSame(TestErrorCode::EmailRequired, $second->code());
    }

    public function test_default_params_contain_field(): void
    {
        $rule = new AlwaysFails();
        $context = new ValidationContext('email', []);

        self::assertSame(['field' => 'email'], $rule->params($context));
    }

    public function test_subclass_can_extend_params(): void
    {
        $rule = new CapturingRule(['min' => 3, 'max' => 50]);
        $context = new ValidationContext('username', []);

        self::assertSame(
            ['field' => 'username', 'min' => 3, 'max' => 50],
            $rule->params($context),
        );
    }
}
