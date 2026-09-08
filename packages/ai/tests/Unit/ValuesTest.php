<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Internal\Values;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValuesTest extends TestCase
{
    #[Test]
    public function stringCoercesWhatAProviderSendsBadly(): void
    {
        self::assertSame('42', Values::string(42));
        self::assertSame('3.5', Values::string(3.5));
        self::assertSame('true', Values::string(true));
        self::assertSame('{"a":1}', Values::string(['a' => 1]));
        self::assertSame(' sms ', Values::string(' sms ', trim: false));
        self::assertSame('sms', Values::string(' sms '));
        self::assertSame('fallback', Values::string(null, 'fallback'));
    }

    #[Test]
    public function anEmptyStringAnswersTheDefaultNotItself(): void
    {
        self::assertSame('max_completion_tokens', Values::string('', 'max_completion_tokens'));
        self::assertSame('https://api.openai.com/v1', Values::string('   ', 'https://api.openai.com/v1'));
        self::assertSame('', Values::string('', ''));
    }

    #[Test]
    public function intKeepsOnlyWholeNumbers(): void
    {
        self::assertSame(7, Values::int('7'));
        self::assertSame(7, Values::int(7.0));
        self::assertSame(1, Values::int(true));
        self::assertSame(5, Values::int('2.5', 5));
        self::assertSame(5, Values::int('', 5));
        self::assertSame(5, Values::int(null, 5));
        self::assertNull(Values::int('junk', null));
    }

    #[Test]
    public function arrayAcceptsArraysObjectsAndJsonStrings(): void
    {
        self::assertSame(['a' => 1], Values::array(['a' => 1]));
        self::assertSame(['a' => 1], Values::array('{"a":1}'));

        $cast = Values::array((object) ['a' => 1]);
        self::assertSame(1, $cast['a']);

        self::assertSame([], Values::array(null));
        self::assertSame([], Values::array('not json'));
        self::assertSame(['x' => 1], Values::array('', ['x' => 1]));
    }
}
