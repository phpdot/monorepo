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
use PHPUnit\Framework\Attributes\Test;
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

    #[Test]
    public function validRangeWithin30DaysPasses(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-01',
            'end_date' => '2024-04-15',
        ], $this->rules());

        self::assertFalse($bag->hasErrors());
    }

    #[Test]
    public function endBeforeStartFailsWithCorrectCode(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-15',
            'end_date' => '2024-04-10',
        ], $this->rules());

        $endErrors = $bag->forContext('end_date');

        self::assertNotEmpty($endErrors);
        self::assertSame(TestErrorCode::EndDateBeforeStart->value, $endErrors[0]->code);
    }

    #[Test]
    public function rangeExceeding30DaysFailsWithRangeTooLongCode(): void
    {
        $bag = $this->validator->validate([
            'start_date' => '2024-04-01',
            'end_date' => '2024-06-01',
        ], $this->rules());

        $endErrors = $bag->forContext('end_date');
        $codes = array_map(fn($e): string => $e->code, $endErrors);

        self::assertContains(TestErrorCode::DateRangeTooLong->value, $codes);
    }

    #[Test]
    public function messageContainsMaxDaysParam(): void
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

    #[Test]
    public function missingStartDateFailsRequired(): void
    {
        $bag = $this->validator->validate([
            'end_date' => '2024-04-15',
        ], $this->rules());

        $startErrors = $bag->forContext('start_date');

        self::assertNotEmpty($startErrors);
        self::assertSame(TestErrorCode::StartDateInvalid->value, $startErrors[0]->code);
    }

    #[Test]
    public function invalidDateStringFails(): void
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
