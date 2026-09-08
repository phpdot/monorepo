<?php

declare(strict_types=1);

namespace PHPdot\Mcp\Tests\Unit;

use PHPdot\Mcp\Tool\ToolArguments;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolArgumentsTest extends TestCase
{
    #[Test]
    public function stringAnswersTheDefaultWhenAbsentOrBlank(): void
    {
        $arguments = new ToolArguments(['name' => '  ', 'channel' => 'sms']);

        self::assertSame('anyone', $arguments->string('who', 'anyone'));
        self::assertSame('anyone', $arguments->string('name', 'anyone'));
        self::assertSame('sms', $arguments->string('channel', 'anyone'));
    }

    #[Test]
    public function stringCoercesWhatAModelSendsBadly(): void
    {
        $arguments = new ToolArguments([
            'count'  => 42,
            'price'  => 3.5,
            'on'     => true,
            'blob'   => ['a' => 1],
            'padded' => ' sms ',
        ]);

        self::assertSame('42', $arguments->string('count'));
        self::assertSame('3.5', $arguments->string('price'));
        self::assertSame('true', $arguments->string('on'));
        self::assertSame('{"a":1}', $arguments->string('blob'));
        self::assertSame('sms', $arguments->string('padded'));
    }

    #[Test]
    public function intCoercesAndClamps(): void
    {
        $arguments = new ToolArguments(['page' => '3', 'wide' => '2.5', 'flag' => true]);

        self::assertSame(3, $arguments->int('page', 1, 1, 100));
        self::assertSame(1, $arguments->int('wide', 1, 1, 100));
        self::assertSame(1, $arguments->int('flag', 5, 1, 100));
        self::assertSame(5, $arguments->int('absent', 5, 1, 100));
        self::assertSame(2, $arguments->int('page', 1, 1, 2));
    }

    #[Test]
    public function listWrapsABareScalarAndDropsEmpties(): void
    {
        $arguments = new ToolArguments([
            'channels' => 'sms',
            'countries' => ['jo', '  ', 'sa'],
            'nothing' => '',
        ]);

        self::assertSame(['sms'], $arguments->list('channels'));
        self::assertSame(['jo', 'sa'], $arguments->list('countries'));
        self::assertSame([], $arguments->list('nothing'));
        self::assertSame([], $arguments->list('absent'));
    }

    #[Test]
    public function integersKeepWholeNumbersOnly(): void
    {
        $arguments = new ToolArguments(['ids' => ['7', 'x', 9, '2.5']]);

        self::assertSame([7, 9], $arguments->integers('ids'));
    }

    #[Test]
    public function flagKeepsAbsenceDistinctFromFalse(): void
    {
        $arguments = new ToolArguments([
            'on' => 'yes',
            'off' => '0',
            'unsettled' => 'maybe',
        ]);

        self::assertTrue($arguments->flag('on'));
        self::assertFalse($arguments->flag('off'));
        self::assertNull($arguments->flag('unsettled'));
        self::assertNull($arguments->flag('absent'));
    }

    #[Test]
    public function hasReportsWhatWasSent(): void
    {
        $arguments = new ToolArguments(['sent' => null]);

        self::assertTrue($arguments->has('sent'));
        self::assertFalse($arguments->has('absent'));
    }
}
