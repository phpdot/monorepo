<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule\Filled;
use PHPdot\Validator\Rule\Nullable;
use PHPdot\Validator\Rule\Present;
use PHPdot\Validator\Rule\Required;
use PHPdot\Validator\Rule\RequiredIf;
use PHPdot\Validator\Rule\RequiredUnless;
use PHPdot\Validator\Rule\RequiredWith;
use PHPdot\Validator\Rule\RequiredWithout;
use PHPdot\Validator\Rule\Sometimes;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class PresenceRulesTest extends TestCase
{
    public function test_required_passes_for_non_empty_value(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertTrue($rule->passes('a@b.com', $context));
    }

    public function test_required_fails_for_missing_field(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', []);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_fails_for_null(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => null]);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_fails_for_empty_string(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => '']);

        self::assertFalse($rule->passes('', $context));
    }

    public function test_required_fails_for_whitespace_only_string(): void
    {
        $rule = new Required();
        $context = new ValidationContext('username', ['username' => '   ']);

        self::assertFalse($rule->passes('   ', $context));
        self::assertFalse($rule->passes("\t\n ", $context));
    }

    public function test_required_fails_for_empty_array(): void
    {
        $rule = new Required();
        $context = new ValidationContext('roles', ['roles' => []]);

        self::assertFalse($rule->passes([], $context));
    }

    public function test_required_passes_for_zero_and_false(): void
    {
        $rule = new Required();

        self::assertTrue($rule->passes(0, new ValidationContext('a', ['a' => 0])));
        self::assertTrue($rule->passes('0', new ValidationContext('a', ['a' => '0'])));
        self::assertTrue($rule->passes(false, new ValidationContext('a', ['a' => false])));
    }

    public function test_filled_passes_when_field_absent(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', []);

        self::assertTrue($rule->passes(null, $context));
    }

    public function test_filled_fails_when_present_but_empty(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', ['phone' => '']);

        self::assertFalse($rule->passes('', $context));
    }

    public function test_filled_passes_when_present_and_non_empty(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', ['phone' => '123']);

        self::assertTrue($rule->passes('123', $context));
    }

    public function test_present_passes_for_present_field_even_if_empty(): void
    {
        $rule = new Present();

        self::assertTrue($rule->passes(null, new ValidationContext('a', ['a' => null])));
        self::assertTrue($rule->passes('', new ValidationContext('a', ['a' => ''])));
        self::assertTrue($rule->passes([], new ValidationContext('a', ['a' => []])));
    }

    public function test_present_fails_for_missing_field(): void
    {
        $rule = new Present();
        $context = new ValidationContext('a', []);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_if_required_when_other_matches(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'business']);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_if_optional_when_other_does_not_match(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'personal']);

        self::assertTrue($rule->passes(null, $context));
    }

    public function test_required_if_passes_when_required_and_filled(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'business', 'vat_id' => 'EG123']);

        self::assertTrue($rule->passes('EG123', $context));
    }

    public function test_required_if_params_include_other_and_values(): void
    {
        $rule = new RequiredIf('type', ['business', 'enterprise']);
        $context = new ValidationContext('vat_id', []);

        self::assertSame(
            ['field' => 'vat_id', 'other' => 'type', 'values' => ['business', 'enterprise']],
            $rule->params($context),
        );
    }

    public function test_required_unless_required_when_other_does_not_match(): void
    {
        $rule = new RequiredUnless('account_type', ['guest']);
        $context = new ValidationContext('email', ['account_type' => 'user']);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_unless_optional_when_other_matches(): void
    {
        $rule = new RequiredUnless('account_type', ['guest']);
        $context = new ValidationContext('email', ['account_type' => 'guest']);

        self::assertTrue($rule->passes(null, $context));
    }

    public function test_required_with_required_when_any_other_present(): void
    {
        $rule = new RequiredWith('first_name', 'last_name');
        $context = new ValidationContext('full_name', ['first_name' => 'Omar']);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_with_optional_when_all_others_absent(): void
    {
        $rule = new RequiredWith('first_name', 'last_name');
        $context = new ValidationContext('full_name', []);

        self::assertTrue($rule->passes(null, $context));
    }

    public function test_required_without_required_when_any_other_missing(): void
    {
        $rule = new RequiredWithout('email', 'phone');
        $context = new ValidationContext('username', ['email' => 'a@b.com']);

        self::assertFalse($rule->passes(null, $context));
    }

    public function test_required_without_optional_when_all_others_present(): void
    {
        $rule = new RequiredWithout('email', 'phone');
        $context = new ValidationContext('username', [
            'email' => 'a@b.com',
            'phone' => '123',
        ]);

        self::assertTrue($rule->passes(null, $context));
    }

    public function test_sometimes_always_passes(): void
    {
        $rule = new Sometimes();

        self::assertTrue($rule->passes(null, new ValidationContext('a', [])));
        self::assertTrue($rule->passes('x', new ValidationContext('a', ['a' => 'x'])));
    }

    public function test_nullable_always_passes(): void
    {
        $rule = new Nullable();

        self::assertTrue($rule->passes(null, new ValidationContext('a', ['a' => null])));
        self::assertTrue($rule->passes('x', new ValidationContext('a', ['a' => 'x'])));
    }
}
