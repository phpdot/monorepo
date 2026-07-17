<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule\ArrayType;
use PHPdot\Validator\Rule\Boolean;
use PHPdot\Validator\Rule\Integer;
use PHPdot\Validator\Rule\Json;
use PHPdot\Validator\Rule\Numeric;
use PHPdot\Validator\Rule\StringType;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class TypeRulesTest extends TestCase
{
    private ValidationContext $context;

    protected function setUp(): void
    {
        $this->context = new ValidationContext('field', []);
    }

    public function test_string_type(): void
    {
        $rule = new StringType();

        self::assertTrue($rule->passes('hello', $this->context));
        self::assertTrue($rule->passes('', $this->context));
        self::assertFalse($rule->passes(123, $this->context));
        self::assertFalse($rule->passes(null, $this->context));
        self::assertFalse($rule->passes(['a'], $this->context));
    }

    public function test_integer(): void
    {
        $rule = new Integer();

        self::assertTrue($rule->passes(42, $this->context));
        self::assertTrue($rule->passes(-7, $this->context));
        self::assertTrue($rule->passes('42', $this->context));
        self::assertTrue($rule->passes('-7', $this->context));
        self::assertFalse($rule->passes(3.14, $this->context));
        self::assertFalse($rule->passes('3.14', $this->context));
        self::assertFalse($rule->passes('abc', $this->context));
        self::assertFalse($rule->passes('', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }

    public function test_numeric(): void
    {
        $rule = new Numeric();

        self::assertTrue($rule->passes(42, $this->context));
        self::assertTrue($rule->passes(3.14, $this->context));
        self::assertTrue($rule->passes('42', $this->context));
        self::assertTrue($rule->passes('3.14', $this->context));
        self::assertTrue($rule->passes('-7.5e2', $this->context));
        self::assertFalse($rule->passes('abc', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
        self::assertFalse($rule->passes(true, $this->context));
    }

    public function test_boolean(): void
    {
        $rule = new Boolean();

        self::assertTrue($rule->passes(true, $this->context));
        self::assertTrue($rule->passes(false, $this->context));
        self::assertTrue($rule->passes(0, $this->context));
        self::assertTrue($rule->passes(1, $this->context));
        self::assertTrue($rule->passes('0', $this->context));
        self::assertTrue($rule->passes('1', $this->context));
        self::assertTrue($rule->passes('true', $this->context));
        self::assertTrue($rule->passes('false', $this->context));
        self::assertTrue($rule->passes('TRUE', $this->context));
        self::assertFalse($rule->passes(2, $this->context));
        self::assertFalse($rule->passes('yes', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }

    public function test_array_type(): void
    {
        $rule = new ArrayType();

        self::assertTrue($rule->passes([], $this->context));
        self::assertTrue($rule->passes([1, 2, 3], $this->context));
        self::assertTrue($rule->passes(['a' => 'b'], $this->context));
        self::assertFalse($rule->passes('not an array', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }

    public function test_json(): void
    {
        $rule = new Json();

        self::assertTrue($rule->passes('{"a":1}', $this->context));
        self::assertTrue($rule->passes('[1,2,3]', $this->context));
        self::assertTrue($rule->passes('"string"', $this->context));
        self::assertTrue($rule->passes('123', $this->context));
        self::assertTrue($rule->passes('null', $this->context));
        self::assertFalse($rule->passes('{a:1}', $this->context));
        self::assertFalse($rule->passes('', $this->context));
        self::assertFalse($rule->passes(['not', 'a', 'string'], $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }
}
