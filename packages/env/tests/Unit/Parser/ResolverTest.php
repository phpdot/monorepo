<?php

declare(strict_types=1);

namespace PHPdot\Env\Tests\Unit\Parser;

use PHPdot\Env\Exception\ParseException;
use PHPdot\Env\Parser\Entry;
use PHPdot\Env\Parser\Resolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResolverTest extends TestCase
{
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new Resolver();
    }

    #[Test]
    public function bracedInterpolation(): void
    {
        $entries = [
            new Entry('BASE', '/app', 1),
            new Entry('DATA_DIR', '${BASE}/data', 2),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('/app/data', $resolved[1]->value);
    }

    #[Test]
    public function unbracedInterpolation(): void
    {
        $entries = [
            new Entry('BASE', '/app', 1),
            new Entry('LOG_DIR', '$BASE/logs', 2),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('/app/logs', $resolved[1]->value);
    }

    #[Test]
    public function nestedInterpolation(): void
    {
        $entries = [
            new Entry('BASE', '/app', 1),
            new Entry('DATA_DIR', '${BASE}/data', 2),
            new Entry('NESTED', '${DATA_DIR}/cache', 3),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('/app/data/cache', $resolved[2]->value);
    }

    #[Test]
    public function missingReferenceLeftEmpty(): void
    {
        $entries = [
            new Entry('MISSING_REF', '${UNDEFINED_VAR}', 1),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('', $resolved[0]->value);
    }

    #[Test]
    public function circularReferenceThrowsParseException(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Circular reference detected');

        $entries = [
            new Entry('A', '${B}', 1),
            new Entry('B', '${C}', 2),
            new Entry('C', '${A}', 3),
        ];

        $this->resolver->resolve($entries);
    }

    #[Test]
    public function singleQuotedEntriesSkipInterpolation(): void
    {
        $entries = [
            new Entry('BASE', '/app', 1),
            new Entry('LITERAL', '${BASE}/no-interp', 2, false),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('${BASE}/no-interp', $resolved[1]->value);
    }

    #[Test]
    public function predefinedValuesUsed(): void
    {
        $entries = [
            new Entry('RESULT', '${PREDEFINED}/path', 1),
        ];

        $resolved = $this->resolver->resolve($entries, ['PREDEFINED' => '/existing']);

        self::assertSame('/existing/path', $resolved[0]->value);
    }

    #[Test]
    public function noInterpolationInPlainValue(): void
    {
        $entries = [
            new Entry('PLAIN', 'no-vars-here', 1),
        ];

        $resolved = $this->resolver->resolve($entries);

        self::assertSame('no-vars-here', $resolved[0]->value);
    }
}
