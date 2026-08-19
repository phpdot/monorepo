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
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComparisonRulesTest extends TestCase
{
    #[Test]
    public function samePassesWhenValuesMatch(): void
    {
        $rule = new Same('password_confirmation');
        $context = new ValidationContext('password', [
            'password' => 'secret',
            'password_confirmation' => 'secret',
        ]);

        self::assertTrue($rule->passes('secret', $context));
    }

    #[Test]
    public function sameFailsWhenValuesDiffer(): void
    {
        $rule = new Same('password_confirmation');
        $context = new ValidationContext('password', [
            'password' => 'secret',
            'password_confirmation' => 'other',
        ]);

        self::assertFalse($rule->passes('secret', $context));
    }

    #[Test]
    public function differentPassesWhenValuesDiffer(): void
    {
        $rule = new Different('email');
        $context = new ValidationContext('username', [
            'username' => 'omar',
            'email' => 'omar@phpdot.com',
        ]);

        self::assertTrue($rule->passes('omar', $context));
    }

    #[Test]
    public function differentFailsWhenValuesMatch(): void
    {
        $rule = new Different('email');
        $context = new ValidationContext('username', [
            'username' => 'a@b.com',
            'email' => 'a@b.com',
        ]);

        self::assertFalse($rule->passes('a@b.com', $context));
    }

    #[Test]
    public function confirmedUsesFieldConfirmationSuffix(): void
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

    #[Test]
    public function confirmedFailsWhenConfirmationMissing(): void
    {
        $rule = new Confirmed();
        $context = new ValidationContext('password', ['password' => 'secret']);

        self::assertFalse($rule->passes('secret', $context));
    }

    #[Test]
    public function gtAgainstLiteral(): void
    {
        $rule = new Gt(18);
        $context = new ValidationContext('age', []);

        self::assertTrue($rule->passes(19, $context));
        self::assertFalse($rule->passes(18, $context));
        self::assertFalse($rule->passes(17, $context));
    }

    #[Test]
    public function gtAgainstOtherField(): void
    {
        $rule = new Gt('min_age');
        $context = new ValidationContext('age', ['min_age' => 18, 'age' => 25]);

        self::assertTrue($rule->passes(25, $context));
        self::assertFalse($rule->passes(18, $context));
    }

    #[Test]
    public function gteAgainstOtherField(): void
    {
        $rule = new Gte('start');
        $context = new ValidationContext('end', ['start' => 5, 'end' => 5]);

        self::assertTrue($rule->passes(5, $context));
        self::assertFalse($rule->passes(4, $context));
    }

    #[Test]
    public function lt(): void
    {
        $rule = new Lt(100);
        $context = new ValidationContext('count', []);

        self::assertTrue($rule->passes(99, $context));
        self::assertFalse($rule->passes(100, $context));
        self::assertFalse($rule->passes(101, $context));
    }

    #[Test]
    public function lte(): void
    {
        $rule = new Lte(100);
        $context = new ValidationContext('count', []);

        self::assertTrue($rule->passes(100, $context));
        self::assertTrue($rule->passes(50, $context));
        self::assertFalse($rule->passes(101, $context));
    }

    #[Test]
    public function in(): void
    {
        $rule = new In('admin', 'editor', 'viewer');
        $context = new ValidationContext('role', []);

        self::assertTrue($rule->passes('admin', $context));
        self::assertTrue($rule->passes('editor', $context));
        self::assertFalse($rule->passes('superadmin', $context));
        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function inUsesStrictComparison(): void
    {
        $rule = new In(1, 2, 3);
        $context = new ValidationContext('n', []);

        self::assertTrue($rule->passes(1, $context));
        self::assertFalse($rule->passes('1', $context));
    }

    #[Test]
    public function notIn(): void
    {
        $rule = new NotIn('reserved', 'admin');
        $context = new ValidationContext('username', []);

        self::assertTrue($rule->passes('omar', $context));
        self::assertFalse($rule->passes('admin', $context));
    }

    #[Test]
    public function startsWith(): void
    {
        $rule = new StartsWith('http://', 'https://');
        $context = new ValidationContext('url', []);

        self::assertTrue($rule->passes('https://example.com', $context));
        self::assertTrue($rule->passes('http://example.com', $context));
        self::assertFalse($rule->passes('ftp://example.com', $context));
        self::assertFalse($rule->passes(null, $context));
    }

    #[Test]
    public function endsWith(): void
    {
        $rule = new EndsWith('.jpg', '.png');
        $context = new ValidationContext('filename', []);

        self::assertTrue($rule->passes('photo.jpg', $context));
        self::assertTrue($rule->passes('photo.png', $context));
        self::assertFalse($rule->passes('photo.gif', $context));
    }
}
