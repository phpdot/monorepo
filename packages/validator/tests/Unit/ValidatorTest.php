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
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(new ErrorBag());
    }

    public function test_no_rules_produces_empty_bag(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], []);

        self::assertFalse($bag->hasErrors());
        self::assertCount(0, $bag);
    }

    public function test_passing_rule_does_not_add_error(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], [
            'email' => [(new AlwaysPasses())->withError(TestErrorCode::Generic)],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    public function test_failing_rule_adds_error_with_field_context(): void
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

    public function test_failing_rule_carries_params_for_interpolation(): void
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

    public function test_multiple_failing_rules_accumulate_errors(): void
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

    public function test_errors_for_multiple_fields_use_correct_context(): void
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

    public function test_failing_rule_without_error_code_throws(): void
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

    public function test_passing_rule_without_error_code_does_not_throw(): void
    {
        $bag = $this->validator->validate(['email' => 'a@b.com'], [
            'email' => [new AlwaysPasses()],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    public function test_non_rule_in_list_throws(): void
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

    public function test_sometimes_skips_chain_when_field_absent(): void
    {
        $bag = $this->validator->validate([], [
            'phone' => [
                new Sometimes(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    public function test_sometimes_does_not_skip_when_field_present(): void
    {
        $bag = $this->validator->validate(['phone' => '123'], [
            'phone' => [
                new Sometimes(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertTrue($bag->hasErrors());
    }

    public function test_nullable_skips_chain_when_value_is_null(): void
    {
        $bag = $this->validator->validate(['phone' => null], [
            'phone' => [
                new Nullable(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertFalse($bag->hasErrors());
    }

    public function test_nullable_does_not_skip_when_value_is_not_null(): void
    {
        $bag = $this->validator->validate(['phone' => '123'], [
            'phone' => [
                new Nullable(),
                (new AlwaysFails())->withError(TestErrorCode::Generic),
            ],
        ]);

        self::assertTrue($bag->hasErrors());
    }

    public function test_validation_context_receives_full_payload(): void
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

    public function test_bail_at_start_stops_chain_on_first_failure(): void
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

    public function test_bail_at_end_stops_chain_on_first_failure(): void
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

    public function test_bail_in_middle_is_position_independent(): void
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

    public function test_bail_does_not_stop_when_rules_pass(): void
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

    public function test_without_bail_chain_runs_all_rules(): void
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
