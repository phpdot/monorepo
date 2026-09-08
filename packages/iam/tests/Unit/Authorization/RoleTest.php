<?php

declare(strict_types=1);

/**
 * The role-name cap counts characters, not bytes: iam_roles.name is
 * VARCHAR(64) under utf8mb4, and a byte cap would refuse names the column
 * itself accepts.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Tests\Unit\Authorization;

use PHPdot\Iam\Authorization\Role;
use PHPdot\Iam\Exception\InvalidRoleNameException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    #[Test]
    public function theNameCapCountsCharactersNotBytes(): void
    {
        $sixtyFour = str_repeat('é', 64);

        self::assertSame(64, mb_strlen($sixtyFour));
        self::assertSame(128, strlen($sixtyFour));

        $role = new Role(1, $sixtyFour);

        self::assertSame($sixtyFour, $role->name);

        $this->expectException(InvalidRoleNameException::class);

        new Role(1, $sixtyFour . 'x');
    }
}
