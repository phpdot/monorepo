<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule\Confirmed;
use PHPdot\Validator\Rule\Different;
use PHPdot\Validator\Rule\EndsWith;
use PHPdot\Validator\Rule\Gt;
use PHPdot\Validator\Rule\Gte;
use PHPdot\Validator\Rule\In;
use PHPdot\Validator\Rule\Lt;
use PHPdot\Validator\Rule\Lte;
use PHPdot\Validator\Rule\NotIn;
use PHPdot\Validator\Rule\Same;
use PHPdot\Validator\Rule\StartsWith;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class ComparisonRulesTest extends TestCase
{
    public function test_same_passes_when_values_match(): void
    {
        $rule = new Same('password_confirmation');
        $context = new ValidationContext('password', [
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        self::assertTrue($rule->passes('secret', $context));
    }

    public function test_same_fails_when_values_differ(): void
    {
        $rule = new Same('password_confirmation');
        $context = new ValidationContext('password', [
            'password' => 'secret',
            'password_confirmation' => 'other',
        ]);

        self::assertFalse($rule->passes('secret', $context));
    }

    public function test_different_passes_when_values_differ(): void
    {
        $rule = new Different('email');
        $context = new ValidationContext('username', [
            'username' => 'omar',
            'email' => 'omar@phpdot.com',
        ]);

        self::assertTrue($rule->passes('omar', $context));
    }

    public function test_different_fails_when_values_match(): void
    {
        $rule = new Different('email');
        $context = new ValidationContext('username', [
            'username' => 'a@b.com',
            'email' => 'a@b.com',
        ]);

        self::assertFalse($rule->passes('a@b.com', $context));
    }

    public function test_confirmed_uses_field_confirmation_suffix(): void
    {
        $rule = new Confirmed();
        $context = new ValidationContext('password', [
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        self::assertTrue($rule->passes('secret', $context));
        self::assertSame(
            ['field' => 'password', 'other' => 'password_confirmation'],
            $rule->params($context),
        );
    }

    public function test_confirmed_fails_when_confirmation_missing(): void
    {
        $rule = new Confirmed();
        $context = new ValidationContext('password', ['password' => 'secret']);

        self::assertFalse($rule->passes('secret', $context));
    }

    public function test_gt_against_literal(): void
    {
        $rule = new Gt(18);
        $context = new ValidationContext('age', []);

        self::assertTrue($rule->passes(19, $context));
        self::assertFalse($rule->passes(18, $context));
        self::assertFalse($rule->passes(17, $context));
    }

    public function test_gt_against_other_field(): void
    {
        $rule = new Gt('min_age');
        $context = new ValidationContext('age', ['min_age' => 18, 'age' => 25]);

        self::assertTrue($rule->passes(25, $context));
        self::assertFalse($rule->passes(18, $context));
    }

    public function test_gte_against_other_field(): void
    {
        $rule = new Gte('start');
        $context = new ValidationContext('end', ['start' => 5, 'end' => 5]);

        self::assertTrue($rule->passes(5, $context));
        self::assertFalse($rule->passes(4, $context));
    }

    public function test_lt(): void
    {
        $rule = new Lt(100);
        $context = new ValidationContext('count', []);

        self::assertTrue($rule->passes(99, $context));
        self::assertFalse($rule->passes(100, $context));
        self::assertFalse($rule->passes(101, $context));
    }

    public function test_lte(): void
    {
        $rule = new Lte(100);
        $context = new ValidationContext('count', []);

        self::assertTrue($rule->passes(100, $context));
        self::assertTrue($rule->passes(50, $context));
        self::assertFalse($rule->passes(101, $context));
    }

    public function test_in(): void
    {
        $rule = new In('admin', 'editor', 'viewer');
        $context = new ValidationContext('role', []);

        self::assertTrue($rule->passes('admin', $context));
        self::assertTrue($rule->passes('editor', $context));
        self::assertFalse($rule->passes('superadmin', $context));
        self::assertFalse($rule->passes(null, $context));
    }

    public function test_in_uses_strict_comparison(): void
    {
        $rule = new In(1, 2, 3);
        $context = new ValidationContext('n', []);

        self::assertTrue($rule->passes(1, $context));
        self::assertFalse($rule->passes('1', $context));
    }

    public function test_not_in(): void
    {
        $rule = new NotIn('reserved', 'admin');
        $context = new ValidationContext('username', []);

        self::assertTrue($rule->passes('omar', $context));
        self::assertFalse($rule->passes('admin', $context));
    }

    public function test_starts_with(): void
    {
        $rule = new StartsWith('http://', 'https://');
        $context = new ValidationContext('url', []);

        self::assertTrue($rule->passes('https://example.com', $context));
        self::assertTrue($rule->passes('http://example.com', $context));
        self::assertFalse($rule->passes('ftp://example.com', $context));
        self::assertFalse($rule->passes(null, $context));
    }

    public function test_ends_with(): void
    {
        $rule = new EndsWith('.jpg', '.png');
        $context = new ValidationContext('filename', []);

        self::assertTrue($rule->passes('photo.jpg', $context));
        self::assertTrue($rule->passes('photo.png', $context));
        self::assertFalse($rule->passes('photo.gif', $context));
    }
}
