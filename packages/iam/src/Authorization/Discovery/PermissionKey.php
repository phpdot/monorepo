<?php

declare(strict_types=1);

/**
 * The permission key format law, in one place: a plain string of letters,
 * digits, `_`, `.`, `:` and `-`, starting with a letter. Structure
 * (`hr:employees.edit-any`) is CONVENTION, not law — the separators exist so
 * humans and the admin surface can group, and nothing is derived from them:
 * the first segment names no owner, because an App is a framework concept
 * and not a string this package invents. Consulted by the scanner at
 * declaration time — never at check time, where an unknown key is simply a
 * deny.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Authorization\Discovery;

final class PermissionKey
{
    private const string PATTERN = '/^[A-Za-z][A-Za-z0-9_.:\-]*$/';

    private const int MAX_LENGTH = 191;

    /**
     * Whether the key satisfies the format law.
     *
     * @param string $key Candidate key
     *
     * @return bool
     */
    public static function valid(string $key): bool
    {
        return preg_match(self::PATTERN, $key) === 1 && strlen($key) <= self::MAX_LENGTH;
    }
}
