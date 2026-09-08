<?php

declare(strict_types=1);

namespace PHPdot\Ai\Tests\Unit;

use PHPdot\Ai\Transport\Json;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonTest extends TestCase
{
    #[Test]
    public function decodeAnswersOnlyObjects(): void
    {
        self::assertSame(['a' => 1], Json::decode('{"a":1}'));
        self::assertNull(Json::decode('"scalar"'));
        self::assertNull(Json::decode('garbage'));
    }

    #[Test]
    public function getWalksAndFailsClosed(): void
    {
        $frame = ['choices' => [['delta' => ['content' => 'hi']]]];

        self::assertSame('hi', Json::get($frame, ['choices', 0, 'delta', 'content']));
        self::assertNull(Json::get($frame, ['choices', 5, 'delta']));
        self::assertNull(Json::get($frame, ['choices', 0, 'missing']));
        self::assertNull(Json::get('scalar', ['choices']));
        self::assertNull(Json::get(null, ['anything']));
    }
}
