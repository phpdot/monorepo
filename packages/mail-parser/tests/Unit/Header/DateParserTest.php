<?php

/**
 * Unit tests for RFC 5322 date parsing including obsolete timezones, military codes, and two-digit years.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

declare(strict_types=1);

namespace PHPdot\Mail\Parser\Tests\Unit\Header;

use PHPdot\Mail\Parser\Header\DateParser;
use PHPUnit\Framework\TestCase;

final class DateParserTest extends TestCase
{
    private DateParser $parser;

    protected function setUp(): void
    {
        $this->parser = new DateParser();
    }

    public function testStandardRfc5322Date(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('15:47:25', $result->format('H:i:s'));
    }

    public function testDateWithoutDayOfWeek(): void
    {
        $result = $this->parser->parse('13 Feb 2020 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
    }

    public function testDateWithComment(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 +0000 (UTC)');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
    }

    public function testObsoleteTimezoneEST(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 10:47:25 EST');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
    }

    public function testObsoleteTimezoneGMT(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 GMT');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
    }

    public function testTwoDigitYearPost2000(): void
    {
        $result = $this->parser->parse('13 Feb 20 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('2020', $result->format('Y'));
    }

    public function testTwoDigitYearPre2000(): void
    {
        $result = $this->parser->parse('13 Feb 95 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('1995', $result->format('Y'));
    }

    public function testDateWithNoSeconds(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47 +0000');
        $this->assertNotNull($result);
        $this->assertSame('15:47', $result->format('H:i'));
    }

    public function testEmptyStringReturnsNull(): void
    {
        $this->assertNull($this->parser->parse(''));
    }

    public function testMalformedDateReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('not a date'));
    }

    public function testDateWithColonTimezone(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 +00:00');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
    }

    public function testMilitaryTimezoneA(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 A');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('+01:00', $result->format('P'));
    }

    public function testMilitaryTimezoneM(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 M');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('+12:00', $result->format('P'));
    }

    public function testMilitaryTimezoneN(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 N');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('-01:00', $result->format('P'));
    }

    public function testMilitaryTimezoneZ(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 Z');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('+00:00', $result->format('P'));
    }

    public function testMilitaryTimezoneY(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 Y');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('-12:00', $result->format('P'));
    }

    public function testObsoleteTimezoneCDT(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 10:47:25 CDT');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('-05:00', $result->format('P'));
    }

    public function testObsoleteTimezonePST(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 07:47:25 PST');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('-08:00', $result->format('P'));
    }

    public function testObsoleteTimezoneMST(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 08:47:25 MST');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('-07:00', $result->format('P'));
    }

    public function testNestedComments(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 +0000 (UTC (Coordinated Universal Time))');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('15:47:25', $result->format('H:i:s'));
    }

    public function testYear49Is2049(): void
    {
        $result = $this->parser->parse('13 Feb 49 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('2049', $result->format('Y'));
    }

    public function testYear50Is1950(): void
    {
        $result = $this->parser->parse('13 Feb 50 15:47:25 +0000');
        $this->assertNotNull($result);
        $this->assertSame('1950', $result->format('Y'));
    }

    public function testCompletelyMalformedDateReturnsNull(): void
    {
        $this->assertNull($this->parser->parse('not-a-date-at-all!!!'));
    }

    public function testDateWithExtraWhitespace(): void
    {
        $result = $this->parser->parse('Thu,  13  Feb  2020  15:47:25  +0000');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('15:47:25', $result->format('H:i:s'));
    }

    public function testEscapedParenInComment(): void
    {
        $result = $this->parser->parse('Thu, 13 Feb 2020 15:47:25 +0000 (comment \) more)');
        $this->assertNotNull($result);
        $this->assertSame('2020-02-13', $result->format('Y-m-d'));
        $this->assertSame('15:47:25', $result->format('H:i:s'));
    }
}
