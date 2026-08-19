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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PresenceRulesTest extends TestCase
{
    #[Test]
    public function requiredPassesForNonEmptyValue(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => 'a@b.com']);

        self::assertTrue($rule->passes('a@b.com', $context));
    }

    #[Test]
    public function requiredFailsForMissingField(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', []);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredFailsForNull(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => null]);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredFailsForEmptyString(): void
    {
        $rule = new Required();
        $context = new ValidationContext('email', ['email' => '']);

        self::assertFalse($rule->passes('', $context));
    }

    #[Test]
    public function requiredFailsForWhitespaceOnlyString(): void
    {
        $rule = new Required();
        $context = new ValidationContext('username', ['username' => '   ']);

        self::assertFalse($rule->passes('   ', $context));
        self::assertFalse($rule->passes("\t\n ", $context));
    }

    #[Test]
    public function requiredFailsForEmptyArray(): void
    {
        $rule = new Required();
        $context = new ValidationContext('roles', ['roles' => []]);

        self::assertFalse($rule->passes([], $context));
    }

    #[Test]
    public function requiredPassesForZeroAndFalse(): void
    {
        $rule = new Required();

        self::assertTrue($rule->passes(0, new ValidationContext('a', ['a' => 0])));
        self::assertTrue($rule->passes('0', new ValidationContext('a', ['a' => '0'])));
        self::assertTrue($rule->passes(false, new ValidationContext('a', ['a' => false])));
    }

    #[Test]
    public function filledPassesWhenFieldAbsent(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', []);

        self::assertTrue($rule->passes(null, $context));
    }

    #[Test]
    public function filledFailsWhenPresentButEmpty(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', ['phone' => '']);

        self::assertFalse($rule->passes('', $context));
    }

    #[Test]
    public function filledPassesWhenPresentAndNonEmpty(): void
    {
        $rule = new Filled();
        $context = new ValidationContext('phone', ['phone' => '123']);

        self::assertTrue($rule->passes('123', $context));
    }

    #[Test]
    public function presentPassesForPresentFieldEvenIfEmpty(): void
    {
        $rule = new Present();

        self::assertTrue($rule->passes(null, new ValidationContext('a', ['a' => null])));
        self::assertTrue($rule->passes('', new ValidationContext('a', ['a' => ''])));
        self::assertTrue($rule->passes([], new ValidationContext('a', ['a' => []])));
    }

    #[Test]
    public function presentFailsForMissingField(): void
    {
        $rule = new Present();
        $context = new ValidationContext('a', []);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredIfRequiredWhenOtherMatches(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'business']);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredIfOptionalWhenOtherDoesNotMatch(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'personal']);

        self::assertTrue($rule->passes(null, $context));
    }

    #[Test]
    public function requiredIfPassesWhenRequiredAndFilled(): void
    {
        $rule = new RequiredIf('type', ['business']);
        $context = new ValidationContext('vat_id', ['type' => 'business', 'vat_id' => 'EG123']);

        self::assertTrue($rule->passes('EG123', $context));
    }

    #[Test]
    public function requiredIfParamsIncludeOtherAndValues(): void
    {
        $rule = new RequiredIf('type', ['business', 'enterprise']);
        $context = new ValidationContext('vat_id', []);

        self::assertSame(
            ['field' => 'vat_id', 'other' => 'type', 'values' => ['business', 'enterprise']],
            $rule->params($context),
        );
    }

    #[Test]
    public function requiredUnlessRequiredWhenOtherDoesNotMatch(): void
    {
        $rule = new RequiredUnless('account_type', ['guest']);
        $context = new ValidationContext('email', ['account_type' => 'user']);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredUnlessOptionalWhenOtherMatches(): void
    {
        $rule = new RequiredUnless('account_type', ['guest']);
        $context = new ValidationContext('email', ['account_type' => 'guest']);

        self::assertTrue($rule->passes(null, $context));
    }

    #[Test]
    public function requiredWithRequiredWhenAnyOtherPresent(): void
    {
        $rule = new RequiredWith('first_name', 'last_name');
        $context = new ValidationContext('full_name', ['first_name' => 'Omar']);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredWithOptionalWhenAllOthersAbsent(): void
    {
        $rule = new RequiredWith('first_name', 'last_name');
        $context = new ValidationContext('full_name', []);

        self::assertTrue($rule->passes(null, $context));
    }

    #[Test]
    public function requiredWithoutRequiredWhenAnyOtherMissing(): void
    {
        $rule = new RequiredWithout('email', 'phone');
        $context = new ValidationContext('username', ['email' => 'a@b.com']);

        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function requiredWithoutOptionalWhenAllOthersPresent(): void
    {
        $rule = new RequiredWithout('email', 'phone');
        $context = new ValidationContext('username', [
            'email' => 'a@b.com',
            'phone' => '123',
        ]);

        self::assertTrue($rule->passes(null, $context));
    }

    #[Test]
    public function sometimesAlwaysPasses(): void
    {
        $rule = new Sometimes();

        self::assertTrue($rule->passes(null, new ValidationContext('a', [])));
        self::assertTrue($rule->passes('x', new ValidationContext('a', ['a' => 'x'])));
    }

    #[Test]
    public function nullableAlwaysPasses(): void
    {
        $rule = new Nullable();

        self::assertTrue($rule->passes(null, new ValidationContext('a', ['a' => null])));
        self::assertTrue($rule->passes('x', new ValidationContext('a', ['a' => 'x'])));
    }
}
