<?php

declare(strict_types=1);

/**
 * A system role (root, guest) was targeted by a destructive operation —
 * reserved roles are structural and undeletable.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Iam\Exception;

final class SystemRoleException extends IamException
{
    /**
     * A refused deletion.
     *
     * @param string $slug The system role
     *
     * @return self
     */
    public static function undeletable(string $slug): self
    {
        return new self(sprintf('Role [%s] is a system role and cannot be deleted.', $slug));
    }

    /**
     * A refused grant or revoke — system roles hold exactly the grants the
     * host provisioned them with; the mutation surface is not where they drift.
     *
     * @param string $slug The system role
     *
     * @return self
     */
    public static function immutable(string $slug): self
    {
        return new self(sprintf(
            'Role [%s] is a system role — its grants are provisioned by the host, not administered through mutations.',
            $slug,
        ));
    }

    /**
     * A refused assignment — root is implied by the catalog, guest by the
     * absence of authentication; neither is ever attached to an identity.
     *
     * @param string $slug The system role
     *
     * @return self
     */
    public static function notAssignable(string $slug): self
    {
        return new self(sprintf('Role [%s] is a system role and cannot be assigned or unassigned.', $slug));
    }

    /**
     * A reserved name arriving through seeds — root and guest are defined by
     * the repository itself and cannot be replaced from the outside.
     *
     * @param string $slug The reserved name
     *
     * @return self
     */
    public static function reserved(string $slug): self
    {
        return new self(sprintf('Role [%s] is reserved and cannot arrive through seeds.', $slug));
    }
}
