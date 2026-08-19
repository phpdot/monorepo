<?php

declare(strict_types=1);

namespace PHPdot\Validator\Tests\Unit\Exception;

use PHPdot\Validator\Exception\MissingErrorCodeException;
use PHPdot\Validator\Tests\Stubs\AlwaysFails;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MissingErrorCodeExceptionTest extends TestCase
{
    #[Test]
    public function exceptionCarriesFieldAndRuleClass(): void
    {
        $e = new MissingErrorCodeException('email', AlwaysFails::class);

        self::assertSame('email', $e->field);
        self::assertSame(AlwaysFails::class, $e->ruleClass);
        self::assertStringContainsString('email', $e->getMessage());
        self::assertStringContainsString(AlwaysFails::class, $e->getMessage());
        self::assertStringContainsString('->withError', $e->getMessage());
    }
}
