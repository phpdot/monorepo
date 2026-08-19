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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NewRulesTest extends TestCase
{
    private function ctx(string $field, array $data): ValidationContext
    {
        /** @var array<string, mixed> $data */
        return new ValidationContext($field, $data);
    }

    // --- Enum ---

    #[Test]
    public function enumPassesForValidCase(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertTrue($rule->passes('admin', $this->ctx('role', ['role' => 'admin'])));
        self::assertTrue($rule->passes('viewer', $this->ctx('role', ['role' => 'viewer'])));
    }

    #[Test]
    public function enumFailsForInvalidCase(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertFalse($rule->passes('superuser', $this->ctx('role', ['role' => 'superuser'])));
    }

    #[Test]
    public function enumFailsForNonScalar(): void
    {
        $rule = new Enum(TestRole::class);
        self::assertFalse($rule->passes(['admin'], $this->ctx('role', ['role' => ['admin']])));
        self::assertFalse($rule->passes(null, $this->ctx('role', ['role' => null])));
    }

    // --- Distinct ---

    #[Test]
    public function distinctPassesForUniqueArray(): void
    {
        $rule = new Distinct();
        self::assertTrue($rule->passes(['a', 'b', 'c'], $this->ctx('tags', ['tags' => ['a', 'b', 'c']])));
        self::assertTrue($rule->passes([], $this->ctx('tags', ['tags' => []])));
    }

    #[Test]
    public function distinctFailsForDuplicates(): void
    {
        $rule = new Distinct();
        self::assertFalse($rule->passes(['a', 'b', 'a'], $this->ctx('tags', ['tags' => ['a', 'b', 'a']])));
    }

    #[Test]
    public function distinctFailsForNonArray(): void
    {
        $rule = new Distinct();
        self::assertFalse($rule->passes('not-an-array', $this->ctx('tags', ['tags' => 'not-an-array'])));
    }

    // --- Lowercase / Uppercase / Ascii ---

    #[Test]
    public function lowercase(): void
    {
        $rule = new Lowercase();
        self::assertTrue($rule->passes('hello', $this->ctx('s', ['s' => 'hello'])));
        self::assertTrue($rule->passes('123!', $this->ctx('s', ['s' => '123!'])));
        self::assertFalse($rule->passes('Hello', $this->ctx('s', ['s' => 'Hello'])));
        self::assertFalse($rule->passes(123, $this->ctx('s', ['s' => 123])));
    }

    #[Test]
    public function uppercase(): void
    {
        $rule = new Uppercase();
        self::assertTrue($rule->passes('HELLO', $this->ctx('s', ['s' => 'HELLO'])));
        self::assertFalse($rule->passes('Hello', $this->ctx('s', ['s' => 'Hello'])));
    }

    #[Test]
    public function ascii(): void
    {
        $rule = new Ascii();
        self::assertTrue($rule->passes('hello', $this->ctx('s', ['s' => 'hello'])));
        self::assertTrue($rule->passes('', $this->ctx('s', ['s' => ''])));
        self::assertFalse($rule->passes('café', $this->ctx('s', ['s' => 'café'])));
        self::assertFalse($rule->passes('مرحبا', $this->ctx('s', ['s' => 'مرحبا'])));
    }

    // --- Digits / DigitsBetween ---

    #[Test]
    public function digitsExactLength(): void
    {
        $rule = new Digits(4);
        self::assertTrue($rule->passes('1234', $this->ctx('otp', ['otp' => '1234'])));
        self::assertTrue($rule->passes(1234, $this->ctx('otp', ['otp' => 1234])));
        self::assertFalse($rule->passes('123', $this->ctx('otp', ['otp' => '123'])));
        self::assertFalse($rule->passes('12345', $this->ctx('otp', ['otp' => '12345'])));
        self::assertFalse($rule->passes('12a4', $this->ctx('otp', ['otp' => '12a4'])));
    }

    #[Test]
    public function digitsBetweenInclusiveRange(): void
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

    #[Test]
    public function prohibitedPassesWhenFieldAbsent(): void
    {
        $rule = new Prohibited();
        self::assertTrue($rule->passes(null, $this->ctx('promo_code', [])));
    }

    #[Test]
    public function prohibitedPassesWhenFieldEmpty(): void
    {
        $rule = new Prohibited();
        self::assertTrue($rule->passes('', $this->ctx('promo_code', ['promo_code' => ''])));
        self::assertTrue($rule->passes(null, $this->ctx('promo_code', ['promo_code' => null])));
    }

    #[Test]
    public function prohibitedFailsWhenFieldHasValue(): void
    {
        $rule = new Prohibited();
        self::assertFalse($rule->passes('SAVE10', $this->ctx('promo_code', ['promo_code' => 'SAVE10'])));
    }

    #[Test]
    public function prohibitedIfOnlyWhenOtherMatches(): void
    {
        $rule = new ProhibitedIf('plan', ['free']);
        // plan = paid → unrestricted
        self::assertTrue($rule->passes('SAVE', $this->ctx('promo_code', ['plan' => 'paid', 'promo_code' => 'SAVE'])));
        // plan = free + value present → fail
        self::assertFalse($rule->passes('SAVE', $this->ctx('promo_code', ['plan' => 'free', 'promo_code' => 'SAVE'])));
        // plan = free + empty → pass
        self::assertTrue($rule->passes('', $this->ctx('promo_code', ['plan' => 'free', 'promo_code' => ''])));
    }

    #[Test]
    public function prohibitedUnlessOtherMatches(): void
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

    #[Test]
    public function missingPassesWhenAbsent(): void
    {
        $rule = new Missing();
        self::assertTrue($rule->passes(null, $this->ctx('legacy_id', [])));
    }

    #[Test]
    public function missingFailsWhenPresentEvenIfEmpty(): void
    {
        $rule = new Missing();
        self::assertFalse($rule->passes(null, $this->ctx('legacy_id', ['legacy_id' => null])));
        self::assertFalse($rule->passes('', $this->ctx('legacy_id', ['legacy_id' => ''])));
    }

    #[Test]
    public function missingIfOnlyWhenOtherMatches(): void
    {
        $rule = new MissingIf('source', ['api']);
        self::assertTrue($rule->passes(null, $this->ctx('csrf', ['source' => 'api'])));
        self::assertFalse($rule->passes('', $this->ctx('csrf', ['source' => 'api', 'csrf' => ''])));
        self::assertTrue($rule->passes(null, $this->ctx('csrf', ['source' => 'web', 'csrf' => 'token'])));
    }

    #[Test]
    public function missingUnlessOtherMatches(): void
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
