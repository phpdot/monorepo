<?php

declare(strict_types=1);

namespace PHPdot\Iam\Tests\Unit\Authorization;

use PHPdot\Iam\Authorization\Discovery\PermissionKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The key format law as a table: a plain string of letters, digits, `_`,
 * `.`, `:` and `-`, starting with a letter. Structure is convention only —
 * separators group for humans, and nothing is derived from them.
 */
final class PermissionKeyTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function keys(): iterable
    {
        yield 'dotted convention' => ['hr.employee.edit', true];
        yield 'colon convention' => ['hr:employees.edit-any', true];
        yield 'single word' => ['reports', true];
        yield 'uppercase allowed' => ['HR:Employees.Edit', true];
        yield 'underscore and digits' => ['app_2:module_9.action', true];
        yield 'deep dotted chain' => ['a.b.c.d.e.f', true];
        yield 'starts with digit' => ['9hr.employee', false];
        yield 'starts with underscore' => ['_hr.employee', false];
        yield 'starts with separator' => [':hr.employee', false];
        yield 'space' => ['hr.emp loyee.edit', false];
        yield 'illegal symbol' => ['hr.employee.edit!', false];
        yield 'empty' => ['', false];
        yield 'at the mirror column cap' => [str_repeat('a', 191), true];
        yield 'beyond the mirror column cap' => [str_repeat('a', 192), false];
    }

    #[Test]
    #[DataProvider('keys')]
    public function theFormatLawHolds(string $key, bool $valid): void
    {
        self::assertSame($valid, PermissionKey::valid($key));
    }
}
