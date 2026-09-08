<?php

declare(strict_types=1);

/**
 * iam's Authorizer as a tool actor — the whole bridge is this delegation.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */

namespace PHPdot\Mcp\Bridge;

use PHPdot\Iam\Authorization\Contract\AuthorizerInterface;
use PHPdot\Mcp\Contract\ToolActorInterface;

final readonly class IamActor implements ToolActorInterface
{
    public function __construct(private AuthorizerInterface $authorizer) {}

    /**
     * @param string $permission The key a tool declared
     *
     * @return bool
     */
    public function can(string $permission): bool
    {
        return $this->authorizer->has($permission);
    }
}
