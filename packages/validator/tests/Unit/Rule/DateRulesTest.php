<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Rule;

use DateTimeImmutable;
use PHPdot\Validator\Rule\After;
use PHPdot\Validator\Rule\AfterOrEqual;
use PHPdot\Validator\Rule\Before;
use PHPdot\Validator\Rule\BeforeOrEqual;
use PHPdot\Validator\Rule\Date;
use PHPdot\Validator\Rule\DateBetween;
use PHPdot\Validator\Rule\DateEquals;
use PHPdot\Validator\Rule\DateFormat;
use PHPdot\Validator\Rule\DaysBetween;
use PHPdot\Validator\ValidationContext;
use PHPUnit\Framework\TestCase;

final class DateRulesTest extends TestCase
{
    public function test_date_accepts_iso_string(): void
    {
        $rule = new Date();
        $context = new ValidationContext('start', []);

        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertTrue($rule->passes('2024-04-15 10:00:00', $context));
        self::assertTrue($rule->passes('next monday', $context));
    }

    public function test_date_accepts_datetime_instance(): void
    {
        $rule = new Date();
        $context = new ValidationContext('start', []);

        self::assertTrue($rule->passes(new DateTimeImmutable(), $context));
    }

    public function test_date_rejects_invalid_strings(): void
    {
        $rule = new Date();
        $context = new ValidationContext('start', []);

        self::assertFalse($rule->passes('not a date', $context));
        self::assertFalse($rule->passes('', $context));
        self::assertFalse($rule->passes(null, $context));
    }

    public function test_date_format_strict(): void
    {
        $rule = new DateFormat('Y-m-d');
        $context = new ValidationContext('d', []);

        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertFalse($rule->passes('2024-4-15', $context));
        self::assertFalse($rule->passes('2024-04-15 10:00', $context));
        self::assertFalse($rule->passes('15/04/2024', $context));
    }

    public function test_date_equals(): void
    {
        $rule = new DateEquals('2024-04-15');
        $context = new ValidationContext('d', []);

        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertTrue($rule->passes('2024-04-15 00:00:00', $context));
        self::assertFalse($rule->passes('2024-04-16', $context));
    }

    public function test_date_equals_with_field_reference(): void
    {
        $rule = new DateEquals('reference');
        $context = new ValidationContext('d', [
            'd' => '2024-04-15',
            'reference' => '2024-04-15',
        ]);

        self::assertTrue($rule->passes('2024-04-15', $context));
    }

    public function test_after_with_literal(): void
    {
        $rule = new After('2024-04-15');
        $context = new ValidationContext('d', []);

        self::assertTrue($rule->passes('2024-04-16', $context));
        self::assertFalse($rule->passes('2024-04-15', $context));
        self::assertFalse($rule->passes('2024-04-14', $context));
    }

    public function test_after_with_field_reference(): void
    {
        $rule = new After('start_date');
        $context = new ValidationContext('end_date', [
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-16',
        ]);

        self::assertTrue($rule->passes('2024-04-16', $context));

        $context2 = new ValidationContext('end_date', [
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-15',
        ]);
        self::assertFalse($rule->passes('2024-04-15', $context2));
    }

    public function test_after_or_equal(): void
    {
        $rule = new AfterOrEqual('start_date');
        $context = new ValidationContext('end_date', [
            'start_date' => '2024-04-15',
        ]);

        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertTrue($rule->passes('2024-04-16', $context));
        self::assertFalse($rule->passes('2024-04-14', $context));
    }

    public function test_before(): void
    {
        $rule = new Before('end_date');
        $context = new ValidationContext('start_date', [
            'end_date' => '2024-04-15',
        ]);

        self::assertTrue($rule->passes('2024-04-14', $context));
        self::assertFalse($rule->passes('2024-04-15', $context));
        self::assertFalse($rule->passes('2024-04-16', $context));
    }

    public function test_before_or_equal(): void
    {
        $rule = new BeforeOrEqual('end_date');
        $context = new ValidationContext('start_date', [
            'end_date' => '2024-04-15',
        ]);

        self::assertTrue($rule->passes('2024-04-14', $context));
        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertFalse($rule->passes('2024-04-16', $context));
    }

    public function test_date_between(): void
    {
        $rule = new DateBetween('2024-04-01', '2024-04-30');
        $context = new ValidationContext('d', []);

        self::assertTrue($rule->passes('2024-04-15', $context));
        self::assertTrue($rule->passes('2024-04-01', $context));
        self::assertTrue($rule->passes('2024-04-30', $context));
        self::assertFalse($rule->passes('2024-03-31', $context));
        self::assertFalse($rule->passes('2024-05-01', $context));
    }

    public function test_days_between_passes_within_max(): void
    {
        $rule = new DaysBetween('start_date', 'end_date', max: 30);
        $context = new ValidationContext('end_date', [
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-30',
        ]);

        self::assertTrue($rule->passes('2024-04-30', $context));
    }

    public function test_days_between_fails_when_exceeded(): void
    {
        $rule = new DaysBetween('start_date', 'end_date', max: 30);
        $context = new ValidationContext('end_date', [
            'start_date' => '2024-04-01',
            'end_date' => '2024-05-15',
        ]);

        self::assertFalse($rule->passes('2024-05-15', $context));
    }

    public function test_days_between_fails_when_either_field_missing(): void
    {
        $rule = new DaysBetween('start_date', 'end_date', max: 30);
        $context = new ValidationContext('end_date', [
            'end_date' => '2024-04-30',
        ]);

        self::assertFalse($rule->passes('2024-04-30', $context));
    }

    public function test_days_between_params_include_all_metadata(): void
    {
        $rule = new DaysBetween('start_date', 'end_date', max: 30);
        $context = new ValidationContext('end_date', []);

        self::assertSame(
            [
                'field' => 'end_date',
                'start' => 'start_date',
                'end' => 'end_date',
                'max' => 30,
            ],
            $rule->params($context),
        );
    }

    public function test_days_between_works_in_either_direction(): void
    {
        $rule = new DaysBetween('start_date', 'end_date', max: 30);
        $context = new ValidationContext('end_date', [
            'start_date' => '2024-05-15',
            'end_date' => '2024-04-01',
        ]);

        self::assertFalse($rule->passes('2024-04-01', $context));
    }
}
