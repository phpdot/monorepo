<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule\Alpha;
use PHPdot\Validator\Rule\AlphaDash;
use PHPdot\Validator\Rule\AlphaNum;
use PHPdot\Validator\Rule\Between;
use PHPdot\Validator\Rule\Email;
use PHPdot\Validator\Rule\Ip;
use PHPdot\Validator\Rule\Ipv4;
use PHPdot\Validator\Rule\Ipv6;
use PHPdot\Validator\Rule\Max;
use PHPdot\Validator\Rule\Min;
use PHPdot\Validator\Rule\Regex;
use PHPdot\Validator\Rule\Size;
use PHPdot\Validator\Rule\Slug;
use PHPdot\Validator\Rule\Url;
use PHPdot\Validator\Rule\Uuid;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SizeAndFormatRulesTest extends TestCase
{
    private ValidationContext $context;

    protected function setUp(): void
    {
        $this->context = new ValidationContext('field', []);
    }

    #[Test]
    public function minOnNumeric(): void
    {
        $rule = new Min(18);

        self::assertTrue($rule->passes(18, $this->context));
        self::assertTrue($rule->passes(20, $this->context));
        self::assertFalse($rule->passes(17, $this->context));
        self::assertSame(['field' => 'field', 'min' => 18], $rule->params($this->context));
    }

    #[Test]
    public function minOnStringUsesLength(): void
    {
        $rule = new Min(3);

        self::assertTrue($rule->passes('abc', $this->context));
        self::assertTrue($rule->passes('abcd', $this->context));
        self::assertFalse($rule->passes('ab', $this->context));
    }

    #[Test]
    public function minOnArrayUsesCount(): void
    {
        $rule = new Min(2);

        self::assertTrue($rule->passes([1, 2], $this->context));
        self::assertTrue($rule->passes([1, 2, 3], $this->context));
        self::assertFalse($rule->passes([1], $this->context));
    }

    #[Test]
    public function max(): void
    {
        $rule = new Max(50);

        self::assertTrue($rule->passes(50, $this->context));
        self::assertTrue($rule->passes('abc', $this->context));
        self::assertFalse($rule->passes(51, $this->context));
        self::assertFalse($rule->passes(str_repeat('x', 51), $this->context));
    }

    #[Test]
    public function between(): void
    {
        $rule = new Between(1, 10);

        self::assertTrue($rule->passes(1, $this->context));
        self::assertTrue($rule->passes(5, $this->context));
        self::assertTrue($rule->passes(10, $this->context));
        self::assertFalse($rule->passes(0, $this->context));
        self::assertFalse($rule->passes(11, $this->context));
        self::assertSame(
            ['field' => 'field', 'min' => 1, 'max' => 10],
            $rule->params($this->context),
        );
    }

    #[Test]
    public function sizeExact(): void
    {
        $rule = new Size(5);

        self::assertTrue($rule->passes(5, $this->context));
        self::assertTrue($rule->passes('abcde', $this->context));
        self::assertTrue($rule->passes([1, 2, 3, 4, 5], $this->context));
        self::assertFalse($rule->passes(4, $this->context));
        self::assertFalse($rule->passes('abc', $this->context));
    }

    #[Test]
    public function email(): void
    {
        $rule = new Email();

        self::assertTrue($rule->passes('a@b.com', $this->context));
        self::assertTrue($rule->passes('omar+filter@phpdot.com', $this->context));
        self::assertFalse($rule->passes('not-an-email', $this->context));
        self::assertFalse($rule->passes('a@', $this->context));
        self::assertFalse($rule->passes('', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }

    #[Test]
    public function url(): void
    {
        $rule = new Url();

        self::assertTrue($rule->passes('https://example.com', $this->context));
        self::assertTrue($rule->passes('http://example.com/path?q=1', $this->context));
        self::assertFalse($rule->passes('not a url', $this->context));
        self::assertFalse($rule->passes(null, $this->context));
    }

    #[Test]
    public function uuid(): void
    {
        $rule = new Uuid();

        self::assertTrue($rule->passes('550e8400-e29b-41d4-a716-446655440000', $this->context));
        self::assertTrue($rule->passes('550E8400-E29B-41D4-A716-446655440000', $this->context));
        self::assertFalse($rule->passes('not-a-uuid', $this->context));
        self::assertFalse($rule->passes('550e8400-e29b-41d4-a716', $this->context));
    }

    #[Test]
    public function ip(): void
    {
        $rule = new Ip();

        self::assertTrue($rule->passes('192.168.1.1', $this->context));
        self::assertTrue($rule->passes('::1', $this->context));
        self::assertFalse($rule->passes('999.0.0.1', $this->context));
    }

    #[Test]
    public function ipv4(): void
    {
        $rule = new Ipv4();

        self::assertTrue($rule->passes('192.168.1.1', $this->context));
        self::assertFalse($rule->passes('::1', $this->context));
        self::assertFalse($rule->passes('not.an.ip.address', $this->context));
    }

    #[Test]
    public function ipv6(): void
    {
        $rule = new Ipv6();

        self::assertTrue($rule->passes('::1', $this->context));
        self::assertTrue($rule->passes('2001:db8::1', $this->context));
        self::assertFalse($rule->passes('192.168.1.1', $this->context));
    }

    #[Test]
    public function regex(): void
    {
        $rule = new Regex('/^[a-z]+$/');

        self::assertTrue($rule->passes('abc', $this->context));
        self::assertFalse($rule->passes('ABC', $this->context));
        self::assertFalse($rule->passes('a1', $this->context));
        self::assertFalse($rule->passes(123, $this->context));
        self::assertSame(
            ['field' => 'field', 'pattern' => '/^[a-z]+$/'],
            $rule->params($this->context),
        );
    }

    #[Test]
    public function alpha(): void
    {
        $rule = new Alpha();

        self::assertTrue($rule->passes('abc', $this->context));
        self::assertTrue($rule->passes('عمر', $this->context));
        self::assertFalse($rule->passes('abc123', $this->context));
        self::assertFalse($rule->passes('abc def', $this->context));
        self::assertFalse($rule->passes('', $this->context));
    }

    #[Test]
    public function alphaNum(): void
    {
        $rule = new AlphaNum();

        self::assertTrue($rule->passes('abc123', $this->context));
        self::assertFalse($rule->passes('abc-123', $this->context));
        self::assertFalse($rule->passes('abc 123', $this->context));
    }

    #[Test]
    public function alphaDash(): void
    {
        $rule = new AlphaDash();

        self::assertTrue($rule->passes('abc_123', $this->context));
        self::assertTrue($rule->passes('abc-def', $this->context));
        self::assertFalse($rule->passes('abc def', $this->context));
        self::assertFalse($rule->passes('abc.def', $this->context));
    }

    #[Test]
    public function slug(): void
    {
        $rule = new Slug();

        self::assertTrue($rule->passes('hello-world', $this->context));
        self::assertTrue($rule->passes('post-42', $this->context));
        self::assertFalse($rule->passes('-hello', $this->context));
        self::assertFalse($rule->passes('hello-', $this->context));
        self::assertFalse($rule->passes('hello--world', $this->context));
        self::assertFalse($rule->passes('Hello-World', $this->context));
    }
}
