<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule\Ascii;
use PHPdot\Validator\Rule\Digits;
use PHPdot\Validator\Rule\DigitsBetween;
use PHPdot\Validator\Rule\Distinct;
use PHPdot\Validator\Rule\Enum;
use PHPdot\Validator\Rule\Lowercase;
use PHPdot\Validator\Rule\Missing;
use PHPdot\Validator\Rule\MissingIf;
use PHPdot\Validator\Rule\MissingUnless;
use PHPdot\Validator\Rule\Prohibited;
use PHPdot\Validator\Rule\ProhibitedIf;
use PHPdot\Validator\Rule\ProhibitedUnless;
use PHPdot\Validator\Rule\Uppercase;
use PHPdot\Validator\Tests\Stubs\TestRole;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class NewRulesTest extends TestCase
{
    private function ctx(string $field, array $data): ValidationContext
    {
        /** @var array<string, mixed> $data */
        return new ValidationContext($field, $data);
    }

    // --- Enum ---

    public function test_enum_passes_for_valid_case(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertTrue($rule->passes('admin', $this->ctx('role', ['role' => 'admin'])));
        self::assertTrue($rule->passes('viewer', $this->ctx('role', ['role' => 'viewer'])));
    }

    public function test_enum_fails_for_invalid_case(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertFalse($rule->passes('superuser', $this->ctx('role', ['role' => 'superuser'])));
    }

    public function test_enum_fails_for_non_scalar(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertFalse($rule->passes(['admin'], $this->ctx('role', ['role' => ['admin']])));
        self::assertFalse($rule->passes(null, $this->ctx('role', ['role' => null])));
    }

    // --- Distinct ---

    public function test_distinct_passes_for_unique_array(): void
    {
        $rule = new Distinct();
        self::assertTrue($rule->passes(['a', 'b', 'c'], $this->ctx('tags', ['tags' => ['a', 'b', 'c']])));
        self::assertTrue($rule->passes([], $this->ctx('tags', ['tags' => []])));
    }

    public function test_distinct_fails_for_duplicates(): void
    {
        $rule = new Distinct();
        self::assertFalse($rule->passes(['a', 'b', 'a'], $this->ctx('tags', ['tags' => ['a', 'b', 'a']])));
    }

    public function test_distinct_fails_for_non_array(): void
    {
        $rule = new Distinct();
        self::assertFalse($rule->passes('not-an-array', $this->ctx('tags', ['tags' => 'not-an-array'])));
    }

    // --- Lowercase / Uppercase / Ascii ---

    public function test_lowercase(): void
    {
        $rule = new Lowercase();
        self::assertTrue($rule->passes('hello', $this->ctx('s', ['s' => 'hello'])));
        self::assertTrue($rule->passes('123!', $this->ctx('s', ['s' => '123!'])));
        self::assertFalse($rule->passes('Hello', $this->ctx('s', ['s' => 'Hello'])));
        self::assertFalse($rule->passes(123, $this->ctx('s', ['s' => 123])));
    }

    public function test_uppercase(): void
    {
        $rule = new Uppercase();
        self::assertTrue($rule->passes('HELLO', $this->ctx('s', ['s' => 'HELLO'])));
        self::assertFalse($rule->passes('Hello', $this->ctx('s', ['s' => 'Hello'])));
    }

    public function test_ascii(): void
    {
        $rule = new Ascii();
        self::assertTrue($rule->passes('hello', $this->ctx('s', ['s' => 'hello'])));
        self::assertTrue($rule->passes('', $this->ctx('s', ['s' => ''])));
        self::assertFalse($rule->passes('café', $this->ctx('s', ['s' => 'café'])));
        self::assertFalse($rule->passes('مرحبا', $this->ctx('s', ['s' => 'مرحبا'])));
    }

    // --- Digits / DigitsBetween ---

    public function test_digits_exact_length(): void
    {
        $rule = new Digits(4);
        self::assertTrue($rule->passes('1234', $this->ctx('otp', ['otp' => '1234'])));
        self::assertTrue($rule->passes(1234, $this->ctx('otp', ['otp' => 1234])));
        self::assertFalse($rule->passes('123', $this->ctx('otp', ['otp' => '123'])));
        self::assertFalse($rule->passes('12345', $this->ctx('otp', ['otp' => '12345'])));
        self::assertFalse($rule->passes('12a4', $this->ctx('otp', ['otp' => '12a4'])));
    }

    public function test_digits_between_inclusive_range(): void
    {
        $rule = new DigitsBetween(2, 4);
        self::assertTrue($rule->passes('12', $this->ctx('n', ['n' => '12'])));
        self::assertTrue($rule->passes('123', $this->ctx('n', ['n' => '123'])));
        self::assertTrue($rule->passes('1234', $this->ctx('n', ['n' => '1234'])));
        self::assertFalse($rule->passes('1', $this->ctx('n', ['n' => '1'])));
        self::assertFalse($rule->passes('12345', $this->ctx('n', ['n' => '12345'])));
        self::assertFalse($rule->passes('12a', $this->ctx('n', ['n' => '12a'])));
    }

    // --- Prohibited family ---

    public function test_prohibited_passes_when_field_absent(): void
    {
        $rule = new Prohibited();
        self::assertTrue($rule->passes(null, $this->ctx('promo_code', [])));
    }

    public function test_prohibited_passes_when_field_empty(): void
    {
        $rule = new Prohibited();
        self::assertTrue($rule->passes('', $this->ctx('promo_code', ['promo_code' => ''])));
        self::assertTrue($rule->passes(null, $this->ctx('promo_code', ['promo_code' => null])));
    }

    public function test_prohibited_fails_when_field_has_value(): void
    {
        $rule = new Prohibited();
        self::assertFalse($rule->passes('SAVE10', $this->ctx('promo_code', ['promo_code' => 'SAVE10'])));
    }

    public function test_prohibited_if_only_when_other_matches(): void
    {
        $rule = new ProhibitedIf('plan', ['free']);
        // plan = paid → unrestricted
        self::assertTrue($rule->passes('SAVE', $this->ctx('promo_code', ['plan' => 'paid', 'promo_code' => 'SAVE'])));
        // plan = free + value present → fail
        self::assertFalse($rule->passes('SAVE', $this->ctx('promo_code', ['plan' => 'free', 'promo_code' => 'SAVE'])));
        // plan = free + empty → pass
        self::assertTrue($rule->passes('', $this->ctx('promo_code', ['plan' => 'free', 'promo_code' => ''])));
    }

    public function test_prohibited_unless_other_matches(): void
    {
        $rule = new ProhibitedUnless('role', ['admin']);
        // role = admin → unrestricted
        self::assertTrue($rule->passes('value', $this->ctx('admin_token', ['role' => 'admin', 'admin_token' => 'value'])));
        // role != admin + value → fail
        self::assertFalse($rule->passes('value', $this->ctx('admin_token', ['role' => 'user', 'admin_token' => 'value'])));
        // role != admin + empty → pass
        self::assertTrue($rule->passes('', $this->ctx('admin_token', ['role' => 'user', 'admin_token' => ''])));
    }

    // --- Missing family ---

    public function test_missing_passes_when_absent(): void
    {
        $rule = new Missing();
        self::assertTrue($rule->passes(null, $this->ctx('legacy_id', [])));
    }

    public function test_missing_fails_when_present_even_if_empty(): void
    {
        $rule = new Missing();
        self::assertFalse($rule->passes(null, $this->ctx('legacy_id', ['legacy_id' => null])));
        self::assertFalse($rule->passes('', $this->ctx('legacy_id', ['legacy_id' => ''])));
    }

    public function test_missing_if_only_when_other_matches(): void
    {
        $rule = new MissingIf('source', ['api']);
        self::assertTrue($rule->passes(null, $this->ctx('csrf', ['source' => 'api'])));
        self::assertFalse($rule->passes('', $this->ctx('csrf', ['source' => 'api', 'csrf' => ''])));
        self::assertTrue($rule->passes(null, $this->ctx('csrf', ['source' => 'web', 'csrf' => 'token'])));
    }

    public function test_missing_unless_other_matches(): void
    {
        $rule = new MissingUnless('mode', ['advanced']);
        // mode = advanced → unrestricted
        self::assertTrue($rule->passes('val', $this->ctx('flag', ['mode' => 'advanced', 'flag' => 'val'])));
        // mode != advanced + present → fail
        self::assertFalse($rule->passes('val', $this->ctx('flag', ['mode' => 'basic', 'flag' => 'val'])));
        // mode != advanced + absent → pass
        self::assertTrue($rule->passes(null, $this->ctx('flag', ['mode' => 'basic'])));
    }
}
