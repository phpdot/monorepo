<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Exception;

use PHPdot\Validator\Exception\MissingErrorCodeException;
use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPUnit\Framework\TestCase;

final class MissingErrorCodeExceptionTest extends TestCase
{
    public function test_exception_carries_field_and_rule_class(): void
    {
        $e = new MissingErrorCodeException('email', AlwaysFails::class);

        self::assertSame('email', $e->field);
        self::assertSame(AlwaysFails::class, $e->ruleClass);
        self::assertStringContainsString('email', $e->getMessage());
        self::assertStringContainsString(AlwaysFails::class, $e->getMessage());
        self::assertStringContainsString('->withError', $e->getMessage());
    }
}
