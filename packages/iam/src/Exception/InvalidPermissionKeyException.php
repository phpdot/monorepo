<?php

declare(strict_types=1);

/**
 * A declared permission key violates the format law (letters, digits, `_`,
 * `.`, `:`, `-`, starting with a letter). Raised at discovery time — a
 * malformed key is a sync error, never a runtime check that quietly denies.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class InvalidPermissionKeyException extends IamException
{
    /**
     * A malformed key, naming its declaration site.
     *
     * @param string $key The offending key
     * @param string $declaredBy Class::CONST that declared it
     *
     * @return self
     */
    public static function at(string $key, string $declaredBy): self
    {
        return new self(sprintf(
            "Invalid permission key [%s] declared at %s — a key is letters, digits, '_', '.', ':' and '-', starting with a letter.",
            $key,
            $declaredBy,
        ));
    }
}
