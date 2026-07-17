<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Integration;

use PHPdot\Error\ErrorBag;
use PHPdot\Validator\Rule\AfterOrEqual;
use PHPdot\Validator\Rule\Date;
use PHPdot\Validator\Rule\DaysBetween;
use PHPdot\Validator\Rule\Required;
use PHPdot\Validator\Tests\Stubs\TestErrorCode;
use PHPdot\Validator\Validator;
use PHPUnit\Framework\TestCase;

final class DateRangeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator(new ErrorBag());
    }

    /**
     * @return array<string, list<\PHPdot\Validator\Contract\RuleInterface>>
     */
    private function rules(): array
    {
        return [
            'start_date' => [
                (new Required())->withError(TestErrorCode::StartDateInvalid),
                (new Date())->withError(TestErrorCode::StartDateInvalid),
            ],
            'end_date' => [
                (new Required())->withError(TestErrorCode::EndDateBeforeStart),
                (new Date())->withError(TestErrorCode::EndDateBeforeStart),
                (new AfterOrEqual('start_date'))->withError(TestErrorCode::EndDateBeforeStart),
                (new DaysBetween('start_date', 'end_date', max: 30))
                    ->withError(TestErrorCode::DateRangeTooLong),
            ],
        ];
    }

    public function test_valid_range_within_30_days_passes(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-15',
        ], $this->rules());

        self::assertFalse($bag->hasErrors());
    }

    public function test_end_before_start_fails_with_correct_code(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-10',
        ], $this->rules());

        $endErrors = $bag->forContext('end_date');

        self::assertNotEmpty($endErrors);
        self::assertSame(TestErrorCode::EndDateBeforeStart->value, $endErrors[0]->code);
    }

    public function test_range_exceeding_30_days_fails_with_range_too_long_code(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-01',
            'end_date' => '2024-06-01',
        ], $this->rules());

        $endErrors = $bag->forContext('end_date');
        $codes = array_map(fn($e): string => $e->code, $endErrors);

        self::assertContains(TestErrorCode::DateRangeTooLong->value, $codes);
    }

    public function test_message_contains_max_days_param(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-01',
            'end_date' => '2024-06-01',
        ], $this->rules());

        $endErrors = $bag->forContext('end_date');
        $rangeErrors = array_filter(
            $endErrors,
            fn($e): bool => $e->code === TestErrorCode::DateRangeTooLong->value,
        );

        $entry = array_values($rangeErrors)[0];
        self::assertSame(30, $entry->params['max']);
        self::assertSame('start_date', $entry->params['start']);
        self::assertSame('end_date', $entry->params['end']);
    }

    public function test_missing_start_date_fails_required(): void
    {
        $bag = $this->validator->validate([
            'end_date' => '2024-04-15',
        ], $this->rules());

        $startErrors = $bag->forContext('start_date');

        self::assertNotEmpty($startErrors);
        self::assertSame(TestErrorCode::StartDateInvalid->value, $startErrors[0]->code);
    }

    public function test_invalid_date_string_fails(): void
    {
        $bag = $this->validator->validate([
            'start_date' => 'not a date',
            'end_date' => '2024-04-15',
        ], $this->rules());

        $startErrors = $bag->forContext('start_date');

        self::assertNotEmpty($startErrors);
        self::assertSame(TestErrorCode::StartDateInvalid->value, $startErrors[0]->code);
    }
}
