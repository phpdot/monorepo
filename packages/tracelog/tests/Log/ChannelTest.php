<?php

declare(strict_types=1);

namespace PHPdot\TraceLog\Tests\Log;

use PHPdot\TraceLog\Log\Channel\Channel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ChannelTest extends TestCase
{
    #[Test]
    public function allCasesHaveUniqueValues(): void
    {
        $values = array_map(
            static fn(Channel $case): string => $case->value,
            Channel::cases(),
        );

        self::assertSame(count($values), count(array_unique($values)));
    }

    #[Test]
    public function allValuesAreLowercaseStrings(): void
    {
        foreach (Channel::cases() as $case) {
            self::assertSame(strtolower($case->value), $case->value);
            self::assertNotEmpty($case->value);
        }
    }
}
