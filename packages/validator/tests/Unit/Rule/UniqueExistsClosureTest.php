<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use PHPdot\Validator\Rule;
use PHPdot\Validator\Rule\ClosureRule;
use PHPdot\Validator\Rule\Exists;
use PHPdot\Validator\Rule\Unique;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UniqueExistsClosureTest extends TestCase
{
    #[Test]
    public function uniquePassesWhenResolverSaysNo(): void
    {
        $rule = new Unique(fn(mixed $v): bool => false);
        $context = new ValidationContext('email', []);

        self::assertTrue($rule->passes('a@b.com', $context));
    }

    #[Test]
    public function uniqueFailsWhenResolverSaysYes(): void
    {
        $rule = new Unique(fn(mixed $v): bool => true);
        $context = new ValidationContext('email', []);

        self::assertFalse($rule->passes('a@b.com', $context));
    }

    #[Test]
    public function uniqueResolverReceivesValueAndContext(): void
    {
        $captured = ['value' => null, 'field' => null];

        $rule = new Unique(function (mixed $v, ValidationContext $ctx) use (&$captured): bool {
            $captured['value'] = $v;
            $captured['field'] = $ctx->field();

            return false;
        });

        $rule->passes('test@example.com', new ValidationContext('email', []));

        self::assertSame('test@example.com', $captured['value']);
        self::assertSame('email', $captured['field']);
    }

    #[Test]
    public function existsPassesWhenResolverSaysYes(): void
    {
        $rule = new Exists(fn(mixed $v): bool => true);

        self::assertTrue($rule->passes(42, new ValidationContext('org_id', [])));
    }

    #[Test]
    public function existsFailsWhenResolverSaysNo(): void
    {
        $rule = new Exists(fn(mixed $v): bool => false);

        self::assertFalse($rule->passes(42, new ValidationContext('org_id', [])));
    }

    #[Test]
    public function closureFactoryCreatesClosureRule(): void
    {
        $rule = Rule::closure(static fn(mixed $v): bool => $v === 'expected');

        self::assertInstanceOf(ClosureRule::class, $rule);
        self::assertTrue($rule->passes('expected', new ValidationContext('a', [])));
        self::assertFalse($rule->passes('other', new ValidationContext('a', [])));
    }

    #[Test]
    public function closureCanReadOtherFieldsViaContext(): void
    {
        $rule = Rule::closure(function (mixed $value, ValidationContext $ctx): bool {
            $start = (string) $ctx->value('start_date');
            $end = (string) $value;

            return strtotime($end) > strtotime($start);
        });

        $context = new ValidationContext('end_date', [
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-20',
        ]);

        self::assertTrue($rule->passes('2024-04-20', $context));

        $context2 = new ValidationContext('end_date', [
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-10',
        ]);

        self::assertFalse($rule->passes('2024-04-10', $context2));
    }
}
